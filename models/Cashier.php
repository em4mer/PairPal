<?php
// models/Cashier.php
require_once __DIR__ . '/User.php';

class Cashier extends User {
    public function canManageProducts(): bool { return false; }
    public function canManageUsers(): bool    { return false; }
    public function canViewReports(): bool    { return false; }
    public function getDashboardTitle(): string { return 'Cashier Dashboard'; }
}
