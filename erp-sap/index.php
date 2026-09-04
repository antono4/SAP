<?php
// ============================================================
// ERP Lite - entry point & router
// ============================================================
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/layout.php';

$page = $_GET['page'] ?? 'dashboard';
$safe = ['dashboard','sales','purchasing','production','warehouse','finance','master'];
if (!in_array($page, $safe)) $page = 'dashboard';

$meta = [
    'dashboard' => 'Dashboard',
    'sales' => 'Sales & Distribution (SD)',
    'purchasing' => 'Materials Management (MM)',
    'production' => 'Production Planning (PP)',
    'warehouse' => 'Warehouse & Stock (WM)',
    'finance' => 'Finance & Accounting (FI/CO)',
    'master' => 'Master Data',
];

page_header($meta[$page], $page);

try {
    $file = __DIR__ . '/pages/' . $page . '.php';
    if (file_exists($file)) {
        require $file;
    } else {
        echo '<div class="alert alert-warning">Halaman tidak ditemukan.</div>';
    }
} catch (Throwable $e) {
    echo '<div class="alert alert-danger"><strong>Error:</strong> ' . esc($e->getMessage()) . '<br><small class="text-muted">' . esc($e->getFile()) . ':' . $e->getLine() . '</small></div>';
}

page_footer();