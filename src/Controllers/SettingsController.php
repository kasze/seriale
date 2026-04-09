<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\AppSettingsService;
use App\Services\AuthService;
use App\Services\ShowSyncService;
use App\Repositories\TrackedShowRepository;

final class SettingsController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private Csrf $csrf,
        private AppSettingsService $settings,
        private Session $session,
        private AuthService $auth,
        private ShowSyncService $sync,
        private TrackedShowRepository $tracked
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): \App\Core\Response
    {
        if ($request->method() === 'POST') {
            $this->csrf->validate((string) $request->input('_csrf'));

            try {
                if ((string) $request->input('action') === 'refresh_tracked') {
                    $userId = $this->auth->id();
                    $showIds = $userId === null ? [] : $this->tracked->trackedShowIds($userId);
                    $result = $this->sync->refreshManyLocalShows($showIds, true);
                    $refreshed = count($result['refreshed']);
                    $failed = count($result['failed']);

                    $message = sprintf('Odświeżono %d obserwowanych seriali.', $refreshed);

                    if ($failed > 0) {
                        $message .= sprintf(' %d nie udalo sie pobrac.', $failed);
                    }

                    $this->session->flash('success', $message);
                } else {
                    $this->settings->save($request->all());
                    $this->session->flash('success', 'Ustawienia zapisane.');
                }
            } catch (\Throwable $throwable) {
                $this->session->flash('error', $throwable->getMessage());
            }

            return $this->redirect('/settings');
        }

        return $this->render('settings/index', [
            'pageTitle' => 'Ustawienia',
            'settings' => $this->settings->all(),
            'definitions' => $this->settings->definitions(),
            'groups' => $this->settings->groups(),
        ]);
    }
}
