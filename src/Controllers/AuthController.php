<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Services\AppSettingsService;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        private AuthService $auth,
        private Csrf $csrf,
        private Session $session,
        private AppSettingsService $settings
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): \App\Core\Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/dashboard');
        }

        return $this->render('auth/login', [
            'pageTitle' => 'Logowanie',
            'identity' => $this->settings->singleUserIdentity(),
            'devResetLink' => flash('dev_reset_link'),
        ]);
    }

    public function login(Request $request): \App\Core\Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));

        $identity = trim((string) $request->input('identity', ''));
        $password = (string) $request->input('password', '');
        $remember = (string) $request->input('remember', '') === '1';
        $this->session->flash('old_input', ['identity' => $identity]);

        if ($this->auth->authenticate($identity, $password, $remember)) {
            $this->session->flash('success', 'Zalogowano pomyslnie.');

            return $this->redirect('/dashboard');
        }

        $this->session->flash('error', 'Login lub haslo sa nieprawidlowe.');

        return $this->redirect('/login');
    }

    public function requestPasswordReset(Request $request): \App\Core\Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));
        $identity = trim((string) $request->input('identity', $this->settings->singleUserIdentity()));
        $this->session->flash('old_input', ['identity' => $identity]);

        try {
            $result = $this->auth->requestPasswordReset($identity, $request->ip());
            $this->session->flash('success', 'Link do resetu hasla zostal wyslany.');

            if ($this->settings->isDevelopment() || (($result['mail']['transport'] ?? '') === 'log') || !($result['mail']['sent'] ?? false)) {
                $this->session->flash('dev_reset_link', $result);
            }
        } catch (\Throwable $throwable) {
            $this->session->flash('error', $throwable->getMessage());
        }

        return $this->redirect('/login');
    }

    public function resetForm(Request $request): \App\Core\Response
    {
        if ($this->auth->check()) {
            return $this->redirect('/dashboard');
        }

        $token = trim((string) $request->query('token', ''));

        if (!$this->auth->validPasswordResetToken($token)) {
            $this->session->flash('error', 'Link resetu hasla jest nieprawidlowy lub wygasl.');

            return $this->redirect('/login');
        }

        return $this->render('auth/reset', [
            'pageTitle' => 'Ustaw nowe haslo',
            'token' => $token,
            'identity' => $this->settings->singleUserIdentity(),
        ]);
    }

    public function resetPassword(Request $request): \App\Core\Response
    {
        $this->csrf->validate((string) $request->input('_csrf'));
        $token = trim((string) $request->input('token', ''));
        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');

        if ($password !== $passwordConfirm) {
            $this->session->flash('error', 'Hasla nie sa takie same.');

            return $this->redirect('/password/reset?token=' . urlencode($token));
        }

        try {
            if ($this->auth->resetPassword($token, $password)) {
                $this->session->flash('success', 'Haslo zostalo ustawione, a sesja jest juz aktywna.');

                return $this->redirect('/dashboard');
            }
        } catch (\Throwable $throwable) {
            $this->session->flash('error', $throwable->getMessage());

            return $this->redirect('/password/reset?token=' . urlencode($token));
        }

        $this->session->flash('error', 'Link resetu hasla jest nieprawidlowy lub wygasl.');

        return $this->redirect('/login');
    }

    public function logout(Request $request): \App\Core\Response
    {
        if ($request->method() === 'POST') {
            $this->csrf->validate((string) $request->input('_csrf'));
        }

        $this->auth->logout();

        return $this->redirect('/login');
    }
}
