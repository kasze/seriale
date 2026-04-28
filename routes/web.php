<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HealthController;
use App\Controllers\SettingsController;
use App\Controllers\ShowController;
use App\Controllers\TopController;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Services\AppSettingsService;
use App\Services\AuthService;
use App\Services\ShowSyncService;

$router = app(Router::class);

$router->get('/', static function (Request $request): Response {
    return app(AuthService::class)->check()
        ? Response::redirect('/dashboard')
        : Response::redirect('/login');
});

$router->get('/login', [AuthController::class, 'index']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/password/forgot', [AuthController::class, 'requestPasswordReset']);
$router->get('/password/reset', [AuthController::class, 'resetForm']);
$router->post('/password/reset', [AuthController::class, 'resetPassword']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->post('/logout', [AuthController::class, 'logout'], true);

$router->get('/dashboard', [DashboardController::class, 'index'], true);
$router->get('/dashboard/timeline', [DashboardController::class, 'timeline'], true);
$router->get('/top', [TopController::class, 'index'], true);
$router->get('/shows/search', [ShowController::class, 'search'], true);
$router->post('/tracked', [ShowController::class, 'addTracked'], true);
$router->post('/tracked/query', [ShowController::class, 'addTrackedByQuery'], true);
$router->get('/tracked', [ShowController::class, 'tracked'], true);
$router->get('/shows/{id}', [ShowController::class, 'detail'], true);
$router->post('/shows/{id}/refresh', [ShowController::class, 'refresh'], true);
$router->post('/shows/{id}/untrack', [ShowController::class, 'untrack'], true);
$router->get('/settings', [SettingsController::class, 'index'], true);
$router->post('/settings', [SettingsController::class, 'index'], true);
$router->get('/health', [HealthController::class, 'index']);
$router->get('/about', [HealthController::class, 'index']);
$router->get('/shows/{id}/refresh', static function (Request $request, string $id): Response {
    return Response::redirect('/shows/' . $id);
}, true);
$router->get('/cron/sync', static function (Request $request): Response {
    $secret = trim((string) $request->query('key', ''));
    $expected = (string) app(AppSettingsService::class)->get('cron_secret', '');

    if ($secret === '' || !hash_equals($expected, $secret)) {
        return Response::json(['status' => 'forbidden'], 403);
    }

    $shows = app(ShowSyncService::class)->refreshDueShows(10);

    return Response::json([
        'status' => 'ok',
        'refreshed' => count($shows),
        'shows' => array_map(static fn (array $show) => [
            'id' => $show['id'],
            'title' => $show['title'],
            'next_episode_air_at' => $show['next_episode_air_at'],
        ], $shows),
    ]);
});
$router->get('/cron/prefetch', static function (Request $request): Response {
    $secret = trim((string) $request->query('key', ''));
    $expected = (string) app(AppSettingsService::class)->get('cron_secret', '');

    if ($secret === '' || !hash_equals($expected, $secret)) {
        return Response::json(['status' => 'forbidden'], 403);
    }

    $topLists = app(App\Services\TopListsService::class)->lists(12);
    $trackedRepo = app(App\Repositories\TrackedShowRepository::class);
    $showRepo = app(App\Repositories\ShowRepository::class);
    $users = app(App\Repositories\UserRepository::class);
    $similar = app(App\Services\SimilarShowsService::class);
    $identity = app(AppSettingsService::class)->singleUserIdentity();
    $user = $users->findByIdentity($identity);
    $showIds = $user === null ? [] : $trackedRepo->trackedShowIds((int) $user['id']);
    $shows = [];

    foreach ($showIds as $showId) {
        $show = $showRepo->findById((int) $showId);

        if ($show !== null) {
            $shows[] = $show;
        }
    }

    $prefetch = $similar->prefetchTracked($shows, 6);

    return Response::json([
        'status' => 'ok',
        'top_lists' => count($topLists),
        'similar_prefetched' => $prefetch['prefetched'],
        'similar_skipped' => $prefetch['skipped'],
        'similar_failed' => count($prefetch['failed']),
    ]);
});
