<?php
// controllers/AuthController.php
require_once __DIR__ . '/../services/UserRepository.php';
require_once __DIR__ . '/../services/ActivityLogger.php';
require_once __DIR__ . '/../services/NotificationManager.php';
require_once __DIR__ . '/../models/Admin.php';
require_once __DIR__ . '/../models/Cashier.php';

class AuthController {
    private UserRepository $repo;

    // Brute-force settings
    private const MAX_ATTEMPTS    = 5;     // failures before lockout
    private const LOCKOUT_SECONDS = 300;   // 5-minute lockout
    private const ATTEMPT_WINDOW  = 600;   // track failures for 10 minutes

    public function __construct() {
        $this->repo = new UserRepository();
    }

    // ── Brute-force helpers (session-based per IP bucket) ────────────────
    private function attemptKey(string $username): string {
        // Key by IP + normalised username so each attacker's counter is independent
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return 'bf_' . md5($ip . strtolower(trim($username)));
    }

    private function isLocked(string $key): bool {
        $data = $_SESSION[$key] ?? null;
        if (!$data || $data['attempts'] < self::MAX_ATTEMPTS) return false;
        // Check whether lockout period has expired
        if (time() - $data['last_attempt'] > self::LOCKOUT_SECONDS) {
            unset($_SESSION[$key]); // reset on expiry
            return false;
        }
        return true;
    }

    private function recordFailure(string $key): int {
        $now  = time();
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => $now];
        // Reset counter if window expired
        if ($now - ($data['first_attempt'] ?? $now) > self::ATTEMPT_WINDOW) {
            $data = ['attempts' => 0, 'first_attempt' => $now];
        }
        $data['attempts']++;
        $data['last_attempt'] = $now;
        $_SESSION[$key] = $data;
        return (int)$data['attempts'];
    }

    private function clearAttempts(string $key): void {
        unset($_SESSION[$key]);
    }

    private function remainingLockout(string $key): int {
        $data = $_SESSION[$key] ?? null;
        if (!$data) return 0;
        $elapsed = time() - ($data['last_attempt'] ?? 0);
        return max(0, self::LOCKOUT_SECONDS - $elapsed);
    }

    // ── Login ──────────────────────────────────────────────────────────────
    public function login(string $username, string $password): array {
        $username = trim($username);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        $key = $this->attemptKey($username);

        // Check lockout BEFORE touching the database
        if ($this->isLocked($key)) {
            $wait = $this->remainingLockout($key);
            $mins = ceil($wait / 60);
            return [
                'success'  => false,
                'message'  => "Too many failed attempts. Please wait {$mins} minute" . ($mins !== 1 ? 's' : '') . " before trying again.",
                'locked'   => true,
                'wait_sec' => $wait,
            ];
        }

        $userData = $this->repo->findByUsername($username);

        // Deliberate constant-time path: always run password_verify to
        // prevent timing attacks that reveal whether a username exists.
        $hash    = $userData['password'] ?? password_hash('dummy', PASSWORD_BCRYPT);
        $correct = password_verify($password, $hash);

        if (!$userData || !$correct) {
            $attempts = $this->recordFailure($key);
            $remaining = self::MAX_ATTEMPTS - $attempts;
            if ($remaining <= 0) {
                $mins = ceil(self::LOCKOUT_SECONDS / 60);
                return ['success' => false, 'message' => "Too many failed attempts. Account locked for {$mins} minutes.", 'locked' => true];
            }
            $hint = $remaining === 1 ? ' (1 attempt remaining)' : " ({$remaining} attempts remaining)";
            return ['success' => false, 'message' => 'Invalid username or password.' . $hint];
        }

        // Successful login — clear failure counter
        $this->clearAttempts($key);

        // Regenerate session ID to prevent fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id']    = $userData['id'];
        $_SESSION['username']   = $userData['username'];
        $_SESSION['name']       = $userData['name'];
        $_SESSION['role']       = $userData['role'];
        $_SESSION['logged_in']  = true;
        $_SESSION['login_notification_dismissed'] = false;

        $this->repo->updateLastLogin($userData['id']);

        // Log login activity (non-fatal)
        try {
            (new ActivityLogger())->log(
                ActivityLogger::TYPE_LOGIN,
                "User logged in: {$userData['username']}",
                "Role: {$userData['role']}",
                $userData['id'],
                $userData['name']
            );
        } catch (\Throwable $e) {}

        return ['success' => true, 'role' => $userData['role'], 'name' => $userData['name']];
    }

    // ── Logout ─────────────────────────────────────────────────────────────
    public function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['logged_in']);
    }

    public function getCurrentUser(): ?User {
        if (!$this->isLoggedIn()) return null;
        $data = $this->repo->findById($_SESSION['user_id']);
        if (!$data) return null;
        return $data['role'] === 'admin' ? new Admin($data) : new Cashier($data);
    }

    public function requireLogin(): void {
        if ($this->isLoggedIn()) return;
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        if ($isPost) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated.', 'redirect' => 'index.php?page=login']);
            exit;
        }
        header('Location: index.php?page=login');
        exit;
    }

    public function requireAdmin(): void {
        $this->requireLogin();
        if (($_SESSION['role'] ?? '') !== 'admin') {
            $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
            if ($isPost) {
                header('Content-Type: application/json', true, 403);
                echo json_encode(['success' => false, 'message' => 'Admin access required.']);
                exit;
            }
            header('Location: index.php?page=dashboard&error=unauthorized');
            exit;
        }
    }

    public static function getLoginNotification(): ?array {
        if (!empty($_SESSION['login_notification_dismissed'])) return null;
        return ['show' => true];
    }
}
