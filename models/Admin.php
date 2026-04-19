<?php
// models/Admin.php
require_once __DIR__ . '/User.php';

class Admin extends User {
    public function canManageProducts(): bool { return true; }
    public function canManageUsers(): bool    { return true; }
    public function canViewReports(): bool    { return true; }
    public function getDashboardTitle(): string { return 'Admin Dashboard'; }
}
