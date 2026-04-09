<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Session;
use App\Repositories\LoginTokenRepository;
use App\Repositories\UserRepository;
use App\Support\Str;
use DateInterval;
use DateTimeImmutable;

final class AuthService
{
    private const SESSION_KEY = 'auth_user_id';
    private const LAST_ACTIVITY_KEY = 'auth_last_activity';
    private const SESSION_TIMEOUT_SECONDS = 28800;
    private const REMEMBER_COOKIE = 'seriale_remember';
    private const REMEMBER_SECONDS = 2592000;

    public function __construct(
        private UserRepository $users,
        private LoginTokenRepository $tokens,
        private AppSettingsService $settings,
        private MailerService $mailer,
        private Session $session,
        private Config $config
    ) {
    }

    public function check(): bool
    {
        $userId = $this->session->get(self::SESSION_KEY);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return $this->loginFromRememberCookie();
        }

        $lastActivity = (int) $this->session->get(self::LAST_ACTIVITY_KEY, 0);

        if ($lastActivity > 0 && (time() - $lastActivity) > self::SESSION_TIMEOUT_SECONDS) {
            $this->session->destroy();

            return $this->loginFromRememberCookie();
        }

        $this->session->put(self::LAST_ACTIVITY_KEY, time());

        return $this->currentUser() !== null;
    }

    public function currentUser(): ?array
    {
        $userId = $this->session->get(self::SESSION_KEY);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        return $this->users->findById((int) $userId);
    }

    public function id(): ?int
    {
        $user = $this->currentUser();

        return $user ? (int) $user['id'] : null;
    }

    public function authenticate(string $identity, string $password, bool $remember = false): bool
    {
        $normalized = mb_strtolower(trim($identity));
        $allowed = $this->settings->singleUserIdentity();

        if ($normalized === '' || $normalized !== $allowed) {
            return false;
        }

        $user = $this->users->findOrCreateSingleUser($normalized);
        $hash = (string) ($user['password_hash'] ?? '');

        if ($hash === '' || !password_verify($password, $hash)) {
            return false;
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        return $this->finalizeLogin((int) $user['id'], $remember);
    }

    public function requestMagicLink(string $identity, string $ip): array
    {
        $normalized = mb_strtolower(trim($identity));
        $allowed = $this->settings->singleUserIdentity();

        if ($normalized === '' || $normalized !== $allowed) {
            throw new HttpException(422, 'Nieprawidlowa tozsamosc logowania.');
        }

        $user = $this->users->findOrCreateSingleUser($normalized);
        $token = Str::random(48);
        $code = strtoupper(substr(Str::random(12), 0, 8));
        $expiresAt = (new DateTimeImmutable('now', app('timezone')))
            ->add(new DateInterval('PT20M'))
            ->format(DATE_ATOM);

        $this->tokens->purgeExpired();
        $this->tokens->create(
            (int) $user['id'],
            $normalized,
            hash('sha256', $token),
            hash('sha256', strtoupper($code)),
            $expiresAt,
            $ip
        );

        $link = url('/login/consume?token=' . urlencode($token));
        $mailResult = $this->mailer->sendMagicLink($normalized, $link, $code, $expiresAt);

        return [
            'identity' => $normalized,
            'code' => $code,
            'link' => $link,
            'expires_at' => $expiresAt,
            'mail' => $mailResult,
        ];
    }

    public function requestPasswordReset(string $identity, string $ip): array
    {
        $normalized = mb_strtolower(trim($identity));
        $allowed = $this->settings->singleUserIdentity();

        if ($normalized === '' || $normalized !== $allowed) {
            throw new HttpException(422, 'Nieprawidlowy login.');
        }

        $user = $this->users->findOrCreateSingleUser($normalized);
        $token = Str::random(48);
        $expiresAt = (new DateTimeImmutable('now', app('timezone')))
            ->add(new DateInterval('PT20M'))
            ->format(DATE_ATOM);

        $this->tokens->purgeExpired();
        $this->tokens->create(
            (int) $user['id'],
            $normalized,
            hash('sha256', $token),
            hash('sha256', Str::random(32)),
            $expiresAt,
            $ip,
            'password_reset'
        );

        $link = url('/password/reset?token=' . urlencode($token));
        $mailResult = $this->mailer->sendPasswordReset($normalized, $link, $expiresAt);

        return [
            'identity' => $normalized,
            'link' => $link,
            'expires_at' => $expiresAt,
            'mail' => $mailResult,
        ];
    }

    public function validPasswordResetToken(string $token): bool
    {
        return $token !== '' && $this->tokens->findValidByTokenHashAndPurpose(hash('sha256', trim($token)), 'password_reset') !== null;
    }

    public function resetPassword(string $token, string $password): bool
    {
        $row = $this->tokens->consumeByTokenHashAndPurpose(hash('sha256', trim($token)), 'password_reset');

        if ($row === null) {
            return false;
        }

        $password = trim($password);

        if (mb_strlen($password) < 10) {
            throw new HttpException(422, 'Haslo musi miec co najmniej 10 znakow.');
        }

        $this->users->updatePasswordHash((int) $row['user_id'], password_hash($password, PASSWORD_DEFAULT));

        return $this->finalizeLogin((int) $row['user_id']);
    }

    public function loginWithToken(string $token): bool
    {
        $row = $this->tokens->consumeByTokenHash(hash('sha256', trim($token)));

        if ($row === null) {
            return false;
        }

        return $this->finalizeLogin((int) $row['user_id']);
    }

    public function loginWithCode(string $identity, string $code): bool
    {
        $normalized = mb_strtolower(trim($identity));
        $row = $this->tokens->consumeByIdentityAndCodeHash($normalized, hash('sha256', strtoupper(trim($code))));

        if ($row === null) {
            return false;
        }

        return $this->finalizeLogin((int) $row['user_id']);
    }

    public function logout(): void
    {
        $this->clearRememberCookie();
        $this->session->destroy();
    }

    private function finalizeLogin(int $userId, bool $remember = false): bool
    {
        $user = $this->users->findById($userId);

        if ($user === null) {
            return false;
        }

        $timestamp = now()->format(DATE_ATOM);

        $this->session->regenerate();
        $this->session->put(self::SESSION_KEY, $userId);
        $this->session->put(self::LAST_ACTIVITY_KEY, time());
        $this->users->markLogin($userId, $timestamp);

        if ($remember) {
            $this->queueRememberCookie($user);
        }

        return true;
    }

    private function loginFromRememberCookie(): bool
    {
        $raw = (string) ($_COOKIE[self::REMEMBER_COOKIE] ?? '');

        if ($raw === '') {
            return false;
        }

        $payload = $this->decodeRememberCookie($raw);

        if ($payload === null || (int) ($payload['exp'] ?? 0) < time()) {
            $this->clearRememberCookie();

            return false;
        }

        $user = $this->users->findById((int) ($payload['uid'] ?? 0));

        if ($user === null || !hash_equals((string) ($payload['pwd'] ?? ''), $this->passwordFingerprint($user))) {
            $this->clearRememberCookie();

            return false;
        }

        return $this->finalizeLogin((int) $user['id'], true);
    }

    private function queueRememberCookie(array $user): void
    {
        $payload = [
            'uid' => (int) $user['id'],
            'exp' => time() + self::REMEMBER_SECONDS,
            'pwd' => $this->passwordFingerprint($user),
        ];

        $body = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}');
        $signature = hash_hmac('sha256', $body, $this->rememberSecret());
        $value = $body . '.' . $signature;

        $this->setRememberCookie($value, (int) $payload['exp']);
    }

    private function decodeRememberCookie(string $value): ?array
    {
        [$body, $signature] = array_pad(explode('.', $value, 2), 2, '');

        if ($body === '' || $signature === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $body, $this->rememberSecret());

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $json = $this->base64UrlDecode($body);
        $payload = json_decode($json, true);

        return is_array($payload) ? $payload : null;
    }

    private function passwordFingerprint(array $user): string
    {
        return hash('sha256', (string) ($user['password_hash'] ?? ''));
    }

    private function rememberSecret(): string
    {
        return (string) $this->config->get('app.secret', 'change-me');
    }

    private function setRememberCookie(string $value, int $expires): void
    {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(self::REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if ($expires <= time()) {
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        } else {
            $_COOKIE[self::REMEMBER_COOKIE] = $value;
        }
    }

    private function clearRememberCookie(): void
    {
        $this->setRememberCookie('', time() - 3600);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value, true) ?: '';
    }
}
