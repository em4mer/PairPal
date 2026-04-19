<?php
// models/User.php

abstract class User {
    protected string $id;
    protected string $username;
    protected string $name;
    protected string $email;
    protected string $role;
    protected string $createdAt;

    public function __construct(array $data) {
        $this->id        = $data['id'] ?? '';
        $this->username  = $data['username'] ?? '';
        $this->name      = $data['name'] ?? '';
        $this->email     = $data['email'] ?? '';
        $this->role      = $data['role'] ?? 'user';
        $this->createdAt = $data['created_at'] ?? date('c');
    }

    public function getId(): string     { return $this->id; }
    public function getUsername(): string { return $this->username; }
    public function getName(): string   { return $this->name; }
    public function getEmail(): string  { return $this->email; }
    public function getRole(): string   { return $this->role; }

    abstract public function canManageProducts(): bool;
    abstract public function canManageUsers(): bool;
    abstract public function canViewReports(): bool;
    abstract public function getDashboardTitle(): string;

    public function toArray(): array {
        return [
            'id'         => $this->id,
            'username'   => $this->username,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role,
            'created_at' => $this->createdAt,
        ];
    }
}
