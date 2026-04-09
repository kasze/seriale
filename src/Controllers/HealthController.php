<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AppSettingsService;
use PDO;

final class HealthController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private PDO $pdo,
        private AppSettingsService $settings
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $dbStatus = 'ok';

        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
        } catch (\Throwable) {
            $dbStatus = 'error';
        }

        $payload = [
            'app' => $this->settings->get('app_name', 'Seriale'),
            'env' => $this->settings->appEnv(),
            'timezone' => $this->settings->timezone(),
            'db' => $dbStatus,
            'providers' => [
                'tvmaze' => $this->settings->providerEnabled('tvmaze'),
                'tmdb' => $this->settings->providerEnabled('tmdb'),
                'omdb' => $this->settings->providerEnabled('omdb'),
            ],
        ];

        if ($request->expectsJson()) {
            return Response::json($payload);
        }

        return $this->render('health/index', [
            'pageTitle' => 'Stan systemu',
            'health' => $payload,
        ]);
    }
}
