<?php
// services/UserRepository.php
require_once __DIR__ . '/FileHandler.php';

class UserRepository extends FileHandler {
    public function __construct() {
        parent::__construct(__DIR__ . '/../data/users.json');
    }

    public function getAll(): array {
        return $this->readAll();
    }

    public function findById(string $id): ?array {
        foreach ($this->readAll() as $user) {
            if ($user['id'] === $id) return $user;
        }
        return null;
    }

    public function findByUsername(string $username): ?array {
        foreach ($this->readAll() as $user) {
            if ($user['username'] === $username) return $user;
        }
        return null;
    }

    public function save(array $record): bool {
        $users = $this->readAll();
        $found = false;
        foreach ($users as &$u) {
            if ($u['id'] === $record['id']) {
                $u = array_merge($u, $record);
                $found = true;
                break;
            }
        }
        if (!$found) $users[] = $record;
        return $this->writeAll($users);
    }

    public function delete(string $id): bool {
        $users = array_filter($this->readAll(), fn($u) => $u['id'] !== $id);
        return $this->writeAll(array_values($users));
    }

    public function updateLastLogin(string $id): void {
        $users = $this->readAll();
        foreach ($users as &$u) {
            if ($u['id'] === $id) {
                $u['last_login'] = date('c');
                break;
            }
        }
        $this->writeAll($users);
    }
}
