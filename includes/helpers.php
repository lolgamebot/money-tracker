<?php
// =====================================================================
//  Money Tracker — Shared Helpers, Reusable Components & Security
// =====================================================================

header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// ---------------------------------------------------------------------
//  Global Constants
// ---------------------------------------------------------------------

// Reusable Tailwind class string for form inputs
if (!defined('INPUT_CLASS')) {
    define('INPUT_CLASS', 'w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm');
}

// ---------------------------------------------------------------------
//  Session & Auth Helpers
// ---------------------------------------------------------------------

function initSecureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
}

function requireLogin() {
    initSecureSession();
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}

function getUserId() {
    return $_SESSION["user_id"] ?? null;
}

// ---------------------------------------------------------------------
//  Output / Formatting Helpers
// ---------------------------------------------------------------------

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function formatMoney($amount) {
    return number_format((float)$amount, 2);
}

// ---------------------------------------------------------------------
//  CSRF Protection
// ---------------------------------------------------------------------

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

// ---------------------------------------------------------------------
//  Alerts & Flash Messages
// ---------------------------------------------------------------------

/**
 * Render error/success alert blocks.
 * Accepts a single message or an array of messages for each type.
 * Success messages are NOT escaped (may contain trusted HTML like links).
 */
function renderAlerts($errors = [], $successes = []) {
    foreach ((array)$errors as $msg) {
        if ($msg !== null && $msg !== '') {
            echo '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">' . e($msg) . '</div>';
        }
    }
    foreach ((array)$successes as $msg) {
        if ($msg !== null && $msg !== '') {
            echo '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm" data-auto-dismiss>' . $msg . '</div>';
        }
    }
}

/** Render a one-time session flash message (then clear it). */
function renderFlash($key = 'flash_success') {
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        echo '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm flex items-center justify-between" data-auto-dismiss><span>' . $msg . '</span></div>';
    }
}

/** Store a one-time flash message for the next request. */
function setFlash($message, $key = 'flash_success') {
    $_SESSION[$key] = $message;
}

/** Legacy alias kept for compatibility. */
function showMessage($error = null, $success = null) {
    renderAlerts($error, $success);
}

// ---------------------------------------------------------------------
//  SVG Icon Library (reusable across all pages)
// ---------------------------------------------------------------------

function svgIcon($name, $class = 'h-4 w-4') {
    $icons = [
        'wallet' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 6v3"/></svg>',
        'plus' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>',
        'category' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
        'refresh' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>',
        'chart' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
        'logout' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>',
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
        'edit' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>',
        'trash' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>',
        'search' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>',
        'calendar' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        'check' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
        'chevron-left' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>',
        'chevron-right' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>',
        'income' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>',
        'expense' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>',
        'balance' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>',
        'month' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>',
        'stop' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
        'clipboard' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
        'key' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>',
        'user-add' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>',
        'login' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>',
        'alert' => '<svg xmlns="http://www.w3.org/2000/svg" class="%CLASS%" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
    ];

    return isset($icons[$name]) ? str_replace('%CLASS%', e($class), $icons[$name]) : '';
}

// ---------------------------------------------------------------------
//  Layout Components
// ---------------------------------------------------------------------

/** Shared <head> + opening <body>. */
function renderHeader($title, $opts = []) {
    $opts = array_merge([
        'bodyClass' => 'bg-[#0a0f1e] min-h-screen text-slate-200',
        'flatpickr' => false,
        'chartjs'   => false,
        'extraHead' => '',
    ], $opts);

    $libs = '';
    if ($opts['flatpickr']) {
        $libs .= "\n    " . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">'
              . "\n    " . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">'
              . "\n    " . '<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>';
    }
    if ($opts['chartjs']) {
        $libs .= "\n    " . '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
    }

    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . e($title) . ' - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>' . $libs . '
    ' . ($opts['flatpickr'] ? '    <style>' . "\n" . flatpickrThemeCss() . '    </style>' . "\n" : '') . '
    ' . $opts['extraHead'] . '
</head>
<body class="' . e($opts['bodyClass']) . '">
';
}

/** Closing scripts + </body></html>. Includes UX micro-interactions. */
function renderFooter($extraScripts = '') {
    echo $extraScripts . '
    <script>
        // Auto-dismiss success/notice alerts after a few seconds
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll("[data-auto-dismiss]").forEach(function(el) {
                setTimeout(function() {
                    el.style.transition = "opacity 0.5s ease";
                    el.style.opacity = "0";
                    setTimeout(function() { el.remove(); }, 600);
                }, 4000);
            });
        });
        // Disable submit buttons while the form is being processed
        document.addEventListener("submit", function(e) {
            var btn = e.target.querySelector("button[type=submit]");
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.style.opacity = "0.6";
                btn.style.pointerEvents = "none";
            }
        });
    </script>
</body>
</html>';
}

/** Flatpickr dark-theme styles (single source of truth). */
function flatpickrThemeCss() {
    return <<<'CSS'
        .flatpickr-calendar {
            background: #111827 !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5) !important;
            border-radius: 0.75rem !important;
            font-family: inherit !important;
            max-width: 90vw !important;
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.selected:focus, .flatpickr-day.selected:hover, .flatpickr-day.selected.prevMonthDay, .flatpickr-day.selected.nextMonthDay {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
        }
        .flatpickr-day:hover {
            background: #1e293b !important;
        }
        .flatpickr-day.today {
            border-color: #6366f1 !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            font-weight: 600 !important;
        }
        .flatpickr-calendar.arrowTop:before { border-bottom-color: #334155 !important; }
        .flatpickr-calendar.arrowTop:after { border-bottom-color: #111827 !important; }
        .flatpickr-calendar.arrowBottom:before { border-top-color: #334155 !important; }
        .flatpickr-calendar.arrowBottom:after { border-top-color: #111827 !important; }
CSS;
}

/** Top navigation bar (shared by all authenticated pages). */
function renderNav() {
    $currentPage = basename($_SERVER["PHP_SELF"]);

    $links = [
        "index.php"     => ["Dashboard", "home"],
        "add.php"       => ["Add Record", "plus"],
        "categories.php"=> ["Categories", "category"],
        "recurring.php" => ["Recurring", "refresh"],
        "charts.php"    => ["Charts", "chart"],
        "profile.php"   => ["Profile", "user"],
    ];

    $navLinks = "";
    foreach ($links as $page => $item) {
        $label = $item[0];
        $icon  = $item[1];
        $isActive = $currentPage === $page ? "text-white bg-slate-700/60 font-semibold" : "text-slate-400 hover:text-white hover:bg-slate-700/40";
        $navLinks .= "<a href='{$page}' class='{$isActive} transition-colors py-2 px-3 rounded-lg text-sm whitespace-nowrap flex items-center gap-2'>" . svgIcon($icon) . $label . "</a>";
    }

    $logoutLink = '<a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors py-2 px-3 rounded-lg hover:bg-slate-700/50 text-sm font-medium flex items-center gap-2">' . svgIcon('logout') . 'Logout</a>';

    echo '
    <nav class="bg-[#111827] border-b border-slate-700 border-l-4 border-l-indigo-500 px-4 py-3 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            <a href="index.php" class="text-white font-bold text-base sm:text-lg tracking-tight flex items-center gap-2">
                ' . svgIcon('wallet', 'h-5 w-5 text-indigo-400') . ' <span>Money Tracker</span>
            </a>

            <!-- Hamburger button (mobile/tablet) -->
            <button id="menuToggle" class="md:hidden text-slate-400 hover:text-white p-2 rounded-lg focus:outline-none focus:bg-slate-800" aria-label="Toggle menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path id="menuIconOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path id="menuIconClose" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" class="hidden"/>
                </svg>
            </button>

            <!-- Desktop links -->
            <div class="hidden md:flex items-center gap-1">
                ' . $navLinks . '
                ' . $logoutLink . '
            </div>
        </div>

        <!-- Mobile menu -->
        <div id="mobileMenu" class="hidden md:hidden mt-3 pb-2 border-t border-slate-700/80 pt-3">
            <div class="flex flex-col gap-1.5 px-1">
                ' . $navLinks . '
                ' . $logoutLink . '
            </div>
        </div>
    </nav>

    <script>
        var menuToggle = document.getElementById("menuToggle");
        var mobileMenu = document.getElementById("mobileMenu");
        var menuIconOpen = document.getElementById("menuIconOpen");
        var menuIconClose = document.getElementById("menuIconClose");

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

// ---------------------------------------------------------------------
//  Data Helpers
// ---------------------------------------------------------------------

/** Fetch all categories for a user (cached per request). */
function getCategories($pdo, $userId) {
    static $cache = [];
    if (!isset($cache[$userId])) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
        $stmt->execute([$userId]);
        $cache[$userId] = $stmt->fetchAll();
    }
    return $cache[$userId];
}

/**
 * Compute the end date for a recurring record based on the chosen end
 * condition. Shared between add.php and edit.php to avoid duplication.
 */
function computeRecurringEndDate($startDate, $interval, $endCondition, $input = []) {
    $endDate = null;
    $start = new DateTime($startDate);

    if ($endCondition === "date" && !empty($input["recurring_end_date"])) {
        $endDate = $input["recurring_end_date"];
    } elseif ($endCondition === "occurrences") {
        $count = max(1, (int)($input["occurrences_count"] ?? 1));
        $step = $count - 1;
        $d = clone $start;
        if ($step > 0) {
            switch ($interval) {
                case 'daily':   $d->modify("+$step days"); break;
                case 'weekly':  $d->modify("+$step weeks"); break;
                case 'monthly': $d->modify("+$step months"); break;
                case 'yearly':  $d->modify("+$step years"); break;
            }
        }
        $endDate = $d->format('Y-m-d');
    } elseif ($endCondition === "period") {
        $num = max(1, (int)($input["period_num"] ?? 1));
        $unit = $input["period_unit"] ?? "months";
        $d = clone $start;
        $d->modify("+$num $unit");
        $endDate = $d->format('Y-m-d');
    }

    return $endDate;
}

