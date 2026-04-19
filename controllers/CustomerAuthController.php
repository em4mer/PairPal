<?php
// controllers/CustomerAuthController.php
require_once __DIR__ . '/../services/CustomerRepository.php';
require_once __DIR__ . '/../models/Customer.php';

class CustomerAuthController {
    private CustomerRepository $repo;
    private string $sessionKey = 'customer';

    public function __construct() {
        $this->repo = new CustomerRepository();
    }

    public function register(array $data): array {
        $errors = Customer::validate($data, true);
        if ($errors) return ['success' => false, 'errors' => $errors];

        // Length caps
        if (mb_strlen($data['username']??'') > 40)  return ['success'=>false,'errors'=>['Username must be 40 characters or fewer.']];
        if (mb_strlen($data['name']??'')     > 80)  return ['success'=>false,'errors'=>['Name must be 80 characters or fewer.']];
        if (mb_strlen($data['address']??'')  > 300) return ['success'=>false,'errors'=>['Address must be 300 characters or fewer.']];
        if (mb_strlen($data['contact']??'')  > 20)  return ['success'=>false,'errors'=>['Contact number must be 20 characters or fewer.']];

        if ($this->repo->findByUsername($data['username'])) {
            return ['success' => false, 'errors' => ['That username is already taken.']];
        }
        if ($this->repo->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['An account with that email already exists.']];
        }

        $id = $this->repo->generateId();
        $record = [
            'id'         => $id,
            'username'   => trim($data['username']),
            'password'   => password_hash(trim($data['password']), PASSWORD_BCRYPT),
            'name'       => trim($data['name']),
            'email'      => strtolower(trim($data['email'])),
            'role'       => 'customer',
            'address'    => trim($data['address'] ?? ''),
            'contact'    => trim($data['contact'] ?? ''),
            'wishlist'   => '[]',
            'created_at' => date('c'),
            'last_login' => null,
        ];

        if (!$this->repo->save($record)) {
            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }

        $this->startSession($record);
        return ['success' => true, 'message' => 'Account created successfully!'];
    }

    public function login(string $username, string $password): array {
        $username = trim($username);
        $password = trim($password);
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        // Try by username first, then email
        $data = $this->repo->findByUsername($username) ?? $this->repo->findByEmail($username);
        if (!$data || !password_verify($password, $data['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        session_regenerate_id(true);
        $this->startSession($data);
        $this->repo->updateLastLogin($data['id']);
        return ['success' => true, 'name' => $data['name']];
    }

    private function startSession(array $data): void {
        $_SESSION[$this->sessionKey] = [
            'id'       => $data['id'],
            'username' => $data['username'],
            'name'     => $data['name'],
            'email'    => $data['email'],
            'role'     => 'customer',
            'logged_in'=> true,
        ];
    }

    public function logout(): void {
        unset($_SESSION[$this->sessionKey]);
    }

    public function isLoggedIn(): bool {
        return !empty($_SESSION[$this->sessionKey]['logged_in']);
    }

    public function getSession(): array {
        return $_SESSION[$this->sessionKey] ?? [];
    }

    public function getCurrentCustomer(): ?Customer {
        if (!$this->isLoggedIn()) return null;
        $data = $this->repo->findById($_SESSION[$this->sessionKey]['id'] ?? '');
        return $data ? new Customer($data) : null;
    }

    public function getCustomerData(): ?array {
        if (!$this->isLoggedIn()) return null;
        return $this->repo->findById($_SESSION[$this->sessionKey]['id'] ?? '');
    }

    public function updateProfile(string $id, array $data): array {
        $existing = $this->repo->findById($id);
        if (!$existing) return ['success' => false, 'message' => 'Account not found.'];

        $existing['name']    = trim($data['name']    ?? $existing['name']);
        $existing['address'] = trim($data['address'] ?? $existing['address']);
        $existing['contact'] = trim($data['contact'] ?? $existing['contact']);

        if (!empty($data['new_password'])) {
            if (!password_verify($data['current_password'] ?? '', $existing['password'])) {
                return ['success' => false, 'message' => 'Current password is incorrect.'];
            }
            if (strlen($data['new_password']) < 6) {
                return ['success' => false, 'message' => 'New password must be at least 6 characters.'];
            }
            $existing['password'] = password_hash($data['new_password'], PASSWORD_BCRYPT);
        }

        $ok = $this->repo->save($existing);
        if ($ok) {
            // Update session name
            $_SESSION[$this->sessionKey]['name'] = $existing['name'];
        }
        return $ok ? ['success' => true, 'message' => 'Profile updated.'] : ['success' => false, 'message' => 'Update failed.'];
    }

    public function toggleWishlist(string $customerId, string $productId): array {
        $data = $this->repo->findById($customerId);
        if (!$data) return ['success' => false, 'message' => 'Account not found.'];
        $wishlist = json_decode($data['wishlist'] ?? '[]', true) ?: [];
        if (in_array($productId, $wishlist)) {
            $wishlist = array_values(array_diff($wishlist, [$productId]));
            $action   = 'removed';
        } else {
            $wishlist[] = $productId;
            $action     = 'added';
        }
        $this->repo->updateWishlist($customerId, $wishlist);
        return ['success' => true, 'action' => $action, 'wishlist' => $wishlist];
    }
}
