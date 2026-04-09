<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\TrackedShowRepository;
use App\Services\AuthService;
use App\Services\TopListsService;

final class TopController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private AuthService $auth,
        private TrackedShowRepository $tracked,
        private TopListsService $tops
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): \App\Core\Response
    {
        $lists = $this->tops->lists(12);
        $userId = $this->auth->id();
        $trackedTmdbMap = $userId === null ? [] : $this->tracked->trackedExternalIdMap($userId, 'tmdb');
        $defaultTab = $lists[0]['key'] ?? 'trending_week';

        foreach ($lists as &$list) {
            $list['items'] = array_map(function (array $item) use ($trackedTmdbMap): array {
                $tmdbId = (string) ($item['tmdb_id'] ?? '');
                $item['is_tracked'] = $tmdbId !== '' && isset($trackedTmdbMap[$tmdbId]);
                $item['tracked_show_id'] = $item['is_tracked'] ? $trackedTmdbMap[$tmdbId] : null;
                $item['local_url'] = $item['tracked_show_id'] ? path_url('/shows/' . (string) $item['tracked_show_id']) : null;

                return $item;
            }, $list['items'] ?? []);
            $list['items'] = array_values(array_filter($list['items'], static fn (array $item): bool => empty($item['is_tracked'])));
        }
        unset($list);

        return $this->render('top/index', [
            'pageTitle' => 'Topki',
            'lists' => $lists,
            'topListsEnabled' => $this->tops->enabled(),
            'defaultTopTab' => $defaultTab,
        ]);
    }
}
