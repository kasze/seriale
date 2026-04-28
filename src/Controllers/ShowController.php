<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\EpisodeRepository;
use App\Repositories\SeasonRepository;
use App\Repositories\ShowRepository;
use App\Repositories\ShowUserStateRepository;
use App\Repositories\TrackedShowRepository;
use App\Services\AppSettingsService;
use App\Services\AuthService;
use App\Services\DashboardService;
use App\Services\ShowSyncService;
use App\Services\SimilarShowsService;
use App\Services\TrackedShowService;
use App\Support\Html;

final class ShowController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private Csrf $csrf,
        private AuthService $auth,
        private ShowSyncService $sync,
        private TrackedShowService $trackedService,
        private TrackedShowRepository $tracked,
        private ShowRepository $shows,
        private EpisodeRepository $episodes,
        private SeasonRepository $seasons,
        private ShowUserStateRepository $state,
        private DashboardService $dashboard,
        private AppSettingsService $settings,
        private SimilarShowsService $similarShows
    ) {
        parent::__construct($view);
    }

    public function search(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));
        $results = [];

        if (mb_strlen($query) >= 2) {
            $results = $this->sync->search($query);
        }

        if ($request->expectsJson()) {
            return Response::json([
                'query' => $query,
                'results' => array_slice($results, 0, 8),
            ]);
        }

        return $this->render('shows/search', [
            'pageTitle' => 'Wyszukiwanie seriali',
            'query' => $query,
            'results' => $results,
        ]);
    }

    public function addTracked(Request $request): Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));

        $userId = $this->auth->id();

        if ($userId === null) {
            return $this->redirect('/login');
        }

        $provider = trim((string) $request->input('provider', 'tvmaze'));
        $sourceId = trim((string) $request->input('source_id', ''));

        try {
            if ($sourceId === '') {
                throw new HttpException(422, 'Brak identyfikatora serialu.');
            }

            $show = $this->trackedService->trackByExternalSource($userId, $provider, $sourceId);
            app(\App\Core\Session::class)->flash('success', 'Serial dodany do obserwowanych.');

            return $this->redirect('/shows/' . $show['id']);
        } catch (\Throwable $throwable) {
            app(\App\Core\Session::class)->flash('error', $throwable->getMessage());

            return $this->redirect('/shows/search');
        }
    }

    public function addTrackedByQuery(Request $request): Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));

        $userId = $this->auth->id();

        if ($userId === null) {
            return $this->redirect('/login');
        }

        $query = trim((string) $request->input('query', ''));
        $yearInput = trim((string) $request->input('year', ''));
        $year = $yearInput !== '' && is_numeric($yearInput) ? (int) $yearInput : null;
        $tmdbId = trim((string) $request->input('tmdb_id', ''));
        $redirectTo = trim((string) $request->input('redirect_to', '/top'));

        try {
            $show = $this->trackedService->trackBySearchQuery($userId, $query, $year, $tmdbId !== '' ? $tmdbId : null);
            app(\App\Core\Session::class)->flash('success', 'Serial dodany do obserwowanych.');

            if ($request->expectsJson()) {
                return Response::json([
                    'status' => 'ok',
                    'show' => [
                        'id' => $show['id'],
                        'title' => $show['title'],
                        'url' => path_url('/shows/' . (string) $show['id']),
                    ],
                    'message' => 'Serial dodany do obserwowanych.',
                ]);
            }

            return $this->redirect('/shows/' . $show['id']);
        } catch (\Throwable $throwable) {
            if ($request->expectsJson()) {
                return Response::json([
                    'status' => 'error',
                    'message' => $throwable->getMessage(),
                ], 422);
            }

            app(\App\Core\Session::class)->flash('error', $throwable->getMessage());

            return $this->redirect($redirectTo !== '' ? $redirectTo : '/top');
        }
    }

    public function tracked(Request $request): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return $this->redirect('/login');
        }

        $sort = (string) $request->query('sort', 'next');
        $data = $this->dashboard->build($userId, null, $sort);

        return $this->render('tracked/index', [
            'pageTitle' => 'Obserwowane seriale',
            'sort' => $sort,
            'items' => $data['tracked'],
        ]);
    }

    public function detail(Request $request, string $id): Response
    {
        $userId = $this->auth->id();

        if ($userId === null) {
            return $this->redirect('/login');
        }

        $show = $this->shows->findById((int) $id);

        if ($show === null) {
            throw new HttpException(404, 'Serial nie został znaleziony.');
        }

        $show = $this->sync->refreshIfStale($show);
        $this->trackedService->markOpened($userId, (int) $show['id']);
        $seasonRows = $this->seasons->groupedForShow((int) $show['id']);
        $episodeRows = $this->episodes->groupedForShow((int) $show['id']);
        $isTracked = $this->tracked->isTracked($userId, (int) $show['id']);
        $similarShows = $this->similarShows->forShow($show, 6);
        $trackedTmdbMap = $this->tracked->trackedExternalIdMap($userId, 'tmdb');
        $similarShows['recommended'] = array_map(fn (array $item): array => $this->annotateTrackedSuggestion($item, $trackedTmdbMap), $similarShows['recommended'] ?? []);
        $similarShows['similar'] = array_map(fn (array $item): array => $this->annotateTrackedSuggestion($item, $trackedTmdbMap), $similarShows['similar'] ?? []);
        $highlight = [
            'last' => $show['last_episode_label'] ?? null,
            'next' => $show['next_episode_label'] ?? null,
        ];

        $seasons = [];

        foreach ($seasonRows as $seasonRow) {
            $seasons[(string) ($seasonRow['season_number'] ?? 'special')] = [
                'season' => $seasonRow,
                'episodes' => [],
            ];
        }

        foreach ($episodeRows as $episode) {
            $key = (string) ($episode['season_number'] ?? 'special');

            if (!isset($seasons[$key])) {
                $seasons[$key] = [
                    'season' => [
                        'season_number' => $episode['season_number'],
                        'name' => $episode['season_name'] ?: 'Odcinki specjalne',
                    ],
                    'episodes' => [],
                ];
            }

            $episode['summary_text'] = Html::stripSummary($episode['summary'] ?? null);
            $episode['status_label'] = !empty($episode['airstamp']) && strtotime((string) $episode['airstamp']) <= time() ? 'Wyemitowany' : 'Nadchodzący';
            $episode['is_latest'] = ($highlight['last'] ?? null) === sprintf('S%02dE%02d', (int) ($episode['season_number'] ?? 0), (int) ($episode['episode_number'] ?? 0));
            $episode['is_next'] = ($highlight['next'] ?? null) === sprintf('S%02dE%02d', (int) ($episode['season_number'] ?? 0), (int) ($episode['episode_number'] ?? 0));
            $seasons[$key]['episodes'][] = $episode;
        }

        return $this->render('shows/detail', [
            'pageTitle' => $show['title'],
            'show' => $show,
            'isTracked' => $isTracked,
            'similarShows' => $similarShows,
            'similarShowsEnabled' => $this->similarShows->enabled(),
            'seasonGroups' => $seasons,
            'settings' => $this->settings->all(),
        ]);
    }

    public function refresh(Request $request, string $id): Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));
        $this->sync->refreshLocalShow((int) $id, true);
        app(\App\Core\Session::class)->flash('success', 'Dane serialu zostały odświeżone.');

        return $this->redirect('/shows/' . $id);
    }

    public function untrack(Request $request, string $id): Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));
        $userId = $this->auth->id();

        if ($userId !== null) {
            $this->trackedService->untrack($userId, (int) $id);
        }

        app(\App\Core\Session::class)->flash('success', 'Serial usunięty z obserwowanych.');

        return $this->redirect('/dashboard');
    }

    private function annotateTrackedSuggestion(array $item, array $trackedTmdbMap): array
    {
        $tmdbId = trim((string) ($item['tmdb_id'] ?? ''));
        $item['is_tracked'] = $tmdbId !== '' && isset($trackedTmdbMap[$tmdbId]);
        $item['tracked_show_id'] = $item['is_tracked'] ? $trackedTmdbMap[$tmdbId] : null;
        $item['local_url'] = $item['tracked_show_id'] ? path_url('/shows/' . (string) $item['tracked_show_id']) : null;

        return $item;
    }
}
