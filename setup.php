<?php

// permissions check

$root    = __DIR__;
$dataDir = $root . '/data';
$imgDir  = $root . '/assets/img/products';

$checks = [];
$errors = [];

// 1. Data directory writable
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
    $checks[] = "✓ Created data/ directory";
} elseif (!is_writable($dataDir)) {
    chmod($dataDir, 0775);
    $checks[] = "✓ Fixed data/ directory permissions (0775)";
} else {
    $checks[] = "✓ data/ directory is writable";
}

// 2. JSON files writable & valid
$jsonFiles = ['users.json','products.json','sales.json','bundles.json',
              'orders.json','reviews.json','pairpal_data.json','inventory_logs.json'];
foreach ($jsonFiles as $file) {
    $path = $dataDir . '/' . $file;
    if (!file_exists($path)) {
        file_put_contents($path, '[]');
        chmod($path, 0664);
        $checks[] = "✓ Created $file";
    } else {
        chmod($path, 0664);
        $contents = file_get_contents($path);
        $decoded  = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = "✗ $file has invalid JSON: " . json_last_error_msg();
        } else {
            $checks[] = "✓ $file OK (" . count($decoded) . " records)";
        }
    }
}

// 3. Image upload directory
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0775, true);
    $checks[] = "✓ Created assets/img/products/ directory";
} else {
    chmod($imgDir, 0775);
    $checks[] = "✓ assets/img/products/ is writable";
}

// 4. PHP session test
session_start();
$_SESSION['setup_test'] = true;
$checks[] = "✓ PHP sessions working";

// 5. Password hash test
$hash   = password_hash('password', PASSWORD_BCRYPT);
$verify = password_verify('password', $hash);
if ($verify) {
    $checks[] = "✓ PHP password_hash/verify working";
} else {
    $errors[] = "✗ password_verify is broken on this server!";
}

if (php_sapi_name() === 'cli'):
    echo "PairPal Setup Check\n";
    echo str_repeat('-', 40) . "\n";
    foreach ($checks as $c) echo $c . "\n";
    if ($errors) { echo "\nErrors:\n"; foreach ($errors as $e) echo $e . "\n"; }
    echo "\n" . (empty($errors) ? "All checks passed!" : count($errors) . " issue(s) found.") . "\n";
else: ?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>PairPal Setup</title>
<style>body{font-family:sans-serif;max-width:600px;margin:2rem auto;padding:1rem}
.ok{color:#2d7a4f}.err{color:#c0392b}pre{background:#f5f5f5;padding:1rem;border-radius:6px}</style>
</head><body>
<h1>◈ PairPal Setup Check</h1>
<?php foreach ($checks as $c): ?><p class="ok"><?= htmlspecialchars($c) ?></p><?php endforeach; ?>
<?php foreach ($errors as $e): ?><p class="err"><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
<?php if (empty($errors)): ?>
<p style="margin-top:1.5rem"><strong>✓ All checks passed!</strong> <a href="index.php?page=login">Go to Login →</a></p>
<?php else: ?>
<p style="margin-top:1.5rem;color:#c0392b"><strong>Fix the errors above, then refresh this page.</strong></p>
<?php endif; ?>
</body></html>
<?php endif;
