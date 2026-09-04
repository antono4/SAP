<?php
// ============================================================
// Layout: header + topbar + sidebar
// ============================================================
function page_header(string $title, string $active = ''): void {
    $tit = $title ? $title . ' — ERP Lite' : 'ERP Lite';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= esc($tit) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="assets/css/app.css" rel="stylesheet">
    </head>
    <body>
    <div class="app-shell">
        <!-- ======= Sidebar ======= -->
        <aside class="app-sidebar" id="sidebar">
            <div class="sidebar-brand">
                <div class="brand-icon"><i class="bi bi-grid-1x2-fill"></i></div>
                <div class="brand-text">
                    <span class="brand-name">ERP<span class="brand-lite"> Lite</span></span>
                    <span class="brand-sub">SAP Business Process</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <?php
                $menus = [
                    'dashboard' => ['bi-speedometer2', 'Dashboard'],
                    'sales' => ['bi-cart3', 'Sales & Distribution'],
                    'purchasing' => ['bi-bag-check', 'Materials Management'],
                    'production' => ['bi-gear-wide-connected', 'Production Planning'],
                    'warehouse' => ['bi-boxes', 'Warehouse & Stock'],
                    'finance' => ['bi-cash-stack', 'Finance & Accounting'],
                    'master' => ['bi-database', 'Master Data'],
                ];
                foreach ($menus as $key => $menu) {
                    $isActive = $active === $key ? ' active' : '';
                    echo '<a href="index.php?page=' . $key . '" class="nav-item' . $isActive . '">
                            <i class="bi ' . $menu[0] . '"></i><span>' . $menu[1] . '</span>
                            </a>';
                }
                ?>
            </nav>
            <div class="sidebar-footer">
                <div class="avatar-circle">S</div>
                <div>
                    <div class="fw-semibold text-white">SAP User</div>
                    <small class="text-white-50">Administrator</small>
                </div>
            </div>
        </aside>

        <!-- ======= Main ======= -->
        <div class="app-main">
            <header class="topbar">
                <button class="btn btn-icon d-lg-none" onclick="document.getElementById(&#39;sidebar&#39;).classList.toggle(&#39;open&#39;)"><i class="bi bi-list"></i></button>
                <div class="topbar-title"><?= esc($title) ?></div>
                <div class="topbar-right">
                    <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('d M Y') ?></span>
                    <a href="index.php?page=dashboard" class="btn btn-icon" title="Refresh"><i class="bi bi-arrow-clockwise"></i></a>
                </div>
            </header>
            <main class="app-content">
    <?php
}

function page_footer(): void {
    ?>
            </main>
            <footer class="app-footer">
                <span>ERP Lite © <?= date('Y') ?> — Simulasi Proses Bisnis ala SAP (FI/CO, SD, MM, PP, WM, PM)</span>
            </footer>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    </body>
    </html>
    <?php
}

// Simple confirm helper for action links
function confirm_action(string $message): string {
    return 'onclick="return confirm(' . "'" . addslashes($message) . "'" . ')"';
}