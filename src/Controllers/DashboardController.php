<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private AuthService $auth,
        private DashboardService $dashboard,
        private UserRepository $users
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): \App\Core\Response
    {
        $user = $this->auth->currentUser();

        if ($user === null) {
            return $this->redirect('/login');
        }

        $sort = (string) $request->query('sort', 'next');
        $allowedSorts = ['next', 'title', 'added'];

        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'next';
        }

        $data = $this->dashboard->build((int) $user['id'], $user['last_seen_at'] ?? null, $sort);
        $this->users->updateLastSeen((int) $user['id'], now()->format(DATE_ATOM));

        return $this->render('dashboard/index', [
            'pageTitle' => 'Pulpit',
            'sort' => $sort,
            'dashboard' => $data,
            'user' => $user,
        ]);
    }
}
