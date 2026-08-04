<?php
// Set Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

// XSS Escaping Helper
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// CSRF Protection Helpers
function getCsrfToken() {
    initSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function renderCsrfInput() {
    $token = getCsrfToken();
    echo '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verifyCsrfToken() {
    initSecureSession();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            die("CSRF token validation failed. Please refresh the page and try again.");
        }
    }
}

// CSRF for GET-based state-changing actions (delete, cancel, etc.)
function getCsrfQueryParam() {
    return '&csrf_token=' . urlencode(getCsrfToken());
}

function verifyCsrfGet() {
    initSecureSession();
    $token = $_GET['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die("CSRF token validation failed. Please refresh the page and try again.");
    }
}

function showMessage($error = null, $success = null) {
    if (isset($error)) {
        echo "<p class='error'>" . e($error) . "</p>";
    }
    if (isset($success)) {
        echo "<p class='success'>" . e($success) . "</p>";
    }
}

function requireLogin() {
    initSecureSession();
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function renderNav() {
    $currentPage = basename($_SERVER["PHP_SELF"]);

    $navIcons = [
        "index.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        "add.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
        "categories.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
        "recurring.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        "charts.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        "profile.php" => '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
    ];

    $links = [
        "index.php" => "Dashboard",
        "add.php" => "Add Record",
        "categories.php" => "Categories",
        "recurring.php" => "Recurring",
        "charts.php" => "Charts",
        "profile.php" => "Profile",
    ];

    $navLinks = "";
    foreach ($links as $page => $label) {
        $icon = $navIcons[$page] ?? '';
        $isActive = $currentPage === $page ? "text-white bg-slate-700/60 font-semibold" : "text-slate-400 hover:text-white hover:bg-slate-700/40";
        $navLinks .= "<a href='{$page}' class='{$isActive} transition-colors py-2 px-3 rounded-lg text-sm whitespace-nowrap flex items-center gap-2'>{$icon}{$label}</a>";
    }

    // Wallet SVG icon for logo (replaces 💰 emoji)
    $walletIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 6v3"/></svg>';

    // Logout icon
    $logoutIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>';

    echo '
    <nav class="bg-[#111827] border-b border-slate-700 border-l-4 border-l-indigo-500 px-4 py-3 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="index.php" class="text-white font-bold text-base sm:text-lg tracking-tight flex items-center gap-2">
                ' . $walletIcon . ' <span>Money Tracker</span>
            </a>

            <!-- Hamburger button (mobile/tablet) -->
            <button id="menuToggle" class="md:hidden text-slate-400 hover:text-white p-2 rounded-lg focus:outline-none focus:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="menuIconOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path id="menuIconClose" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" class="hidden"/>
                </svg>
            </button>

            <!-- Desktop links -->
            <div class="hidden md:flex items-center gap-1">
                ' . $navLinks . '
                <a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm font-medium flex items-center gap-2">' . $logoutIcon . 'Logout</a>
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobileMenu" class="hidden md:hidden mt-3 pb-2 border-t border-slate-700/80 pt-3">
            <div class="flex flex-col gap-1.5 px-1">
                ' . $navLinks . '
                <a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm font-medium flex items-center gap-2">' . $logoutIcon . 'Logout</a>
            </div>
        </div>
    </nav>

    <script>
        const menuToggle = document.getElementById("menuToggle");
        const mobileMenu = document.getElementById("mobileMenu");
        const menuIconOpen = document.getElementById("menuIconOpen");
        const menuIconClose = document.getElementById("menuIconClose");

        if (menuToggle) {
            menuToggle.addEventListener("click", function() {
                mobileMenu.classList.toggle("hidden");
                menuIconOpen.classList.toggle("hidden");
                menuIconClose.classList.toggle("hidden");
            });
        }
    </script>
    ';
}
?>