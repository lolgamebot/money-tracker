<?php
// =====================================================================
//  Money Tracker — Shared Helpers, Reusable Components & Security
// =====================================================================

// Lock PHP's "today" to the app's local timezone. Without this, PHP falls
// back to the server's default (usually UTC), so a record dated "today" by
// the user can register as tomorrow (or vice versa) once compared against
// the server's own date('Y-m-d'). That mismatch is what made today-dated
// records show up under Upcoming Bills while yesterday-dated ones didn't,
// and what forced a manual "Confirm Paid" click just to get today's daily
// occurrence counted.
date_default_timezone_set('Asia/Manila');

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

    // Session inactivity timeout (auto-logout after 30 minutes idle)
    $timeout = 60 * 30; // 30 minutes
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > $timeout) {
            // Session expired — destroy it, then regenerate the session ID
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
            // Set the expired flag so the next request can show the re-auth prompt
            $_SESSION['session_expired'] = true;
        } else {
            $_SESSION['last_activity'] = time();
        }
    } elseif (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
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
//  AJAX / Modal Helpers
// ---------------------------------------------------------------------

/** Detect whether the current request is an AJAX (fetch/XHR) request. */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/** Respond with JSON and stop execution. */
function respondJson($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ---------------------------------------------------------------------
//  Alerts & Flash Messages
// ---------------------------------------------------------------------

/**
 * Render error/success alert blocks.
 * Accepts a single message or an array of messages for each type.
 * All shared alert content is HTML-escaped by default.
 */
function renderAlerts($errors = [], $successes = []) {
    foreach ((array)$errors as $msg) {
        if ($msg !== null && $msg !== '') {
            echo '<div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">' . e($msg) . '</div>';
        }
    }
    foreach ((array)$successes as $msg) {
        if ($msg !== null && $msg !== '') {
            echo '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm" data-auto-dismiss>' . e($msg) . '</div>';
        }
    }
}

/** Render trusted HTML success alerts only for callers that intentionally allow markup. */
function renderTrustedAlerts($errors = [], $successes = []) {
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
        echo '<div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm flex items-center justify-between" data-auto-dismiss><span>' . e($msg) . '</span></div>';
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
$libs .= "\n    " . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" crossorigin="anonymous">'
              . "\n    " . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/themes/dark.css" crossorigin="anonymous">'
              . "\n    " . '<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js" crossorigin="anonymous"></script>';
    }
if ($opts['chartjs']) {
        $libs .= "\n    " . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" crossorigin="anonymous"></script>';
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
    <div id="toastContainer" class="fixed top-4 right-4 z-[200] flex flex-col gap-2 pointer-events-none"></div>
    <script>
        // Toast notification helper (used throughout the app).
        function showToast(message, type) {
            type = type || "success";
            var container = document.getElementById("toastContainer");
            if (!container) return;
            var toast = document.createElement("div");
            var icon = type === "error"
                ? "<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-5 w-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z\"/></svg> "
                : "<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-5 w-5\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\" stroke-width=\"2\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M5 13l4 4L19 7\"/></svg> ";
            var colors = type === "error"
                ? "text-rose-400 border-rose-500/30 bg-rose-500/10"
                : "text-emerald-400 border-emerald-500/30 bg-emerald-500/10";
            toast.className = "pointer-events-auto flex items-center gap-2 px-4 py-3 rounded-lg border text-sm font-medium shadow-lg transition-all duration-300 " + colors;
            toast.style.opacity = "0";
            toast.style.transform = "translateX(20px)";
            toast.innerHTML = icon + "<span>" + message + "</span>";
            container.appendChild(toast);
            requestAnimationFrame(function() {
                toast.style.opacity = "1";
                toast.style.transform = "translateX(0)";
            });
            setTimeout(function() {
                toast.style.opacity = "0";
                toast.style.transform = "translateX(20px)";
                setTimeout(function() { toast.remove(); }, 300);
            }, 3000);
        }
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
    $onDashboard = $currentPage === "index.php";

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
        // On the dashboard, nav items open as popups; elsewhere they navigate normally.
        $modalAttr = ($onDashboard && $page !== "index.php") ? " data-modal-uri='{$page}'" : "";
        $navLinks .= "<a href='{$page}'{$modalAttr} class='{$isActive} transition-colors py-2 px-3 rounded-lg text-sm whitespace-nowrap flex items-center gap-2'>" . svgIcon($icon) . $label . "</a>";
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

/**
 * Popup (modal) system for the dashboard.
 *
 * Renders an overlay + dialog and wires up all the JS needed to:
 *  - open a modal from an element with `data-modal-uri`
 *  - fetch the target page with `?modal=1` (AJAX) and inject its content
 *  - re-run inline <script> tags so flatpickr / Chart.js / toggles init
 *  - intercept form submits and same-origin links inside the modal and
 *    handle them via AJAX (JSON `{success:true}` closes + reloads)
 *
 * Only needed on the dashboard (index.php).
 */
function renderModalSystem() {
    echo '
    <div id="modalOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] hidden items-start justify-center overflow-y-auto py-4 sm:py-10 px-4">
<div id="modalDialog" class="relative bg-[#0a0f1e] border border-slate-700 rounded-2xl w-full max-w-4xl shadow-2xl my-auto">
            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-700">
                <h3 id="modalTitle" class="text-lg font-bold text-white">Loading...</h3>
                <button id="modalClose" class="text-slate-400 hover:text-white transition-colors p-1 rounded-lg hover:bg-slate-800" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <!-- Body -->
            <div id="modalBody" class="p-5 max-h-[75vh] overflow-y-auto">
                <div class="flex items-center justify-center py-10 text-slate-400">
                    <svg class="animate-spin h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var overlay  = document.getElementById("modalOverlay");
        var dialog   = document.getElementById("modalDialog");
        var body     = document.getElementById("modalBody");
        var title    = document.getElementById("modalTitle");
        var closeBtn = document.getElementById("modalClose");

        function openModal() {
            overlay.classList.remove("hidden");
            overlay.classList.add("flex");
            document.body.style.overflow = "hidden";
        }
        function closeModal() {
            overlay.classList.add("hidden");
            overlay.classList.remove("flex");
            document.body.style.overflow = "";
            body.innerHTML = "";
        }
        closeBtn.addEventListener("click", closeModal);
        overlay.addEventListener("click", function(e) {
            if (e.target === overlay) closeModal();
        });
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") closeModal();
        });

        // Re-execute inline scripts in a given container (so flatpickr/charts/toggles init).
        function initModalScripts(container) {
            container.querySelectorAll("script").forEach(function(oldScript) {
                var newScript = document.createElement("script");
                newScript.text = oldScript.text;
                oldScript.parentNode.replaceChild(newScript, oldScript);
            });
        }

        // Fetch a modal URI and inject its content.
        function fetchModal(uri) {
            openModal();
            title.textContent = "Loading...";
            body.innerHTML = \'<div class="flex items-center justify-center py-10 text-slate-400"><svg class="animate-spin h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Loading...</div>\';

            fetch(uri + (uri.indexOf("?") === -1 ? "?" : "&") + "modal=1", {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                // Extract optional title from a <title data-modal-title> tag if present.
                var titleMatch = html.match(/<title data-modal-title>([^<]*)<\/title>/);
                if (titleMatch) title.textContent = titleMatch[1];
                body.innerHTML = html;
                initModalScripts(body);
            })
            .catch(function() {
                body.innerHTML = \'<div class="text-center py-10 text-rose-400">Failed to load.</div>\';
            });
        }

        // Handle navigation & form submission inside the modal via AJAX.
        function handleAjax(uri, method, formData) {
            openModal();
            title.textContent = "Processing...";
            body.setAttribute("data-loading", "1");
            body.innerHTML = \'<div class="flex items-center justify-center py-10 text-slate-400"><svg class="animate-spin h-6 w-6 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>Processing...</div>\';

            fetch(uri, {
                method: method,
                headers: { "X-Requested-With": "XMLHttpRequest" },
                body: formData
            })
            .then(function(r) {
                var ct = r.headers.get("Content-Type") || "";
                if (ct.indexOf("application/json") !== -1) {
                    return r.json().then(function(data) { return { json: data }; });
                }
                return r.text().then(function(t) { return { text: t }; });
            })
            .then(function(res) {
                if (res.json) {
                    if (res.json.success) {
                        closeModal();
                        window.location.reload();
                    } else {
                        // Re-render form with validation errors (HTML).
                        if (res.json.html) {
                            body.innerHTML = res.json.html;
                            initModalScripts(body);
                            title.textContent = res.json.title || "Form";
                        } else {
                            body.innerHTML = \'<div class="text-center py-10 text-rose-400">Something went wrong.</div>\';
                        }
                    }
                } else {
                    // Follow a redirect target (e.g. edit form) — re-fetch in modal.
                    body.innerHTML = res.text;
                    initModalScripts(body);
                }
            })
            .catch(function() {
                body.innerHTML = \'<div class="text-center py-10 text-rose-400">An error occurred.</div>\';
            });
        }

        // Global click handler for modal triggers.
        document.addEventListener("click", function(e) {
            var trigger = e.target.closest ? e.target.closest("[data-modal-uri]") : null;
            if (trigger) {
                e.preventDefault();
                fetchModal(trigger.getAttribute("data-modal-uri"));
                return;
            }

            // Same-origin links inside the modal body.
            if (body.contains(e.target)) {
                var link = e.target.closest ? e.target.closest("a[href]") : null;
                if (link) {
                    var href = link.getAttribute("href");
                    if (href && href.indexOf("http") !== 0 && href.charAt(0) !== "#") {
                        // Let delete.php / logout.php etc. navigate normally.
                        if (href.indexOf("delete.php") !== -1 || href.indexOf("logout.php") !== -1) {
                            return;
                        }
                        if (link.hasAttribute("onclick")) {
                            // Let confirm() run; if it returns false, stop.
                            var handler = link.getAttribute("onclick");
                            if (handler.indexOf("return false") !== -1) {
                                return;
                            }
                        }
                        e.preventDefault();
                        handleAjax(href, "GET", null);
                    }
                }
            }
        });

        // Global form submit handler for forms inside the modal.
        document.addEventListener("submit", function(e) {
            var form = e.target;
            if (form && body.contains(form)) {
                e.preventDefault();
                handleAjax(form.getAttribute("action") || window.location.href, form.method || "POST", new FormData(form));
            }
        });
    })();
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
    $allowedIntervals = ['daily', 'weekly', 'monthly', 'yearly'];

    if ($endCondition === "date" && !empty($input["recurring_end_date"])) {
        $endDate = $input["recurring_end_date"];
    } elseif ($endCondition === "occurrences") {
        if (!in_array($interval, $allowedIntervals, true)) {
            return null;
        }

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
        $unit = $input["period_unit"] ?? 'months';
        $allowedUnits = ['day', 'days', 'week', 'weeks', 'month', 'months', 'year', 'years'];
        if (!in_array($unit, $allowedUnits, true)) {
            $unit = 'months';
        }

    }

return $endDate;
}

// ---------------------------------------------------------------------
//  Error Logging
// ---------------------------------------------------------------------

/**
 * Append a message to the logs/error.log file (auto-creates the directory).
 * Returns false if logging fails.
 */
function logError($message) {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $logDir = dirname(__DIR__, 3) . '/logs';
    $logDir = realpath($logDir) ?: $logDir;

    if ($documentRoot !== '' && strpos(realpath($logDir) ?: $logDir, $documentRoot) === 0) {
        $logDir = sys_get_temp_dir() . '/moneytracker-logs';
    }

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }

    $logFile = $logDir . '/error.log';
    $maxBytes = 1024 * 1024;
    if (is_file($logFile) && filesize($logFile) > $maxBytes) {
        @rename($logFile, $logFile . '.' . date('YmdHis'));
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $handle = @fopen($logFile, 'ab');
    if (!$handle) {
        return false;
    }

    if (!@flock($handle, LOCK_EX)) {
        @fclose($handle);
        return false;
    }

    $written = @fwrite($handle, $line);
    @flock($handle, LOCK_UN);
    @fclose($handle);

    return $written !== false;
}

// ---------------------------------------------------------------------
//  Password Strength
// ---------------------------------------------------------------------

/**
 * Compute a password strength score 0-4 based on length and character
 * variety. Used by the client-side meter and the server-side check.
 */
function passwordStrengthScore($password) {
    $score = 0;
    if (strlen($password) >= 8) {
        $score++;
    }
    if (preg_match('/[A-Z]/', $password)) {
        $score++;
    }
    if (preg_match('/[0-9]/', $password)) {
        $score++;
    }
    if (preg_match('/[^A-Za-z0-9]/', $password)) {
        $score++;
    }
    return $score;
}

// ---------------------------------------------------------------------
//  Upcoming Bills / Payments
// ---------------------------------------------------------------------

/**
 * Check whether a column exists in a table (cached per request).
 * Used to gracefully degrade the upcoming-bills feature when the
 * schema has not yet been updated (e.g. missing `paid` column).
 */
function columnExists($pdo, $table, $column) {
    static $cache = [];
    $key = $table . '.' . $column;
    if (!isset($cache[$key])) {
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
            ");
            $stmt->execute([$table, $column]);
            $cache[$key] = (int)$stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            $cache[$key] = false;
        }
    }
    return $cache[$key];
}

/**
 * Fetch "upcoming bills" for the current month.
 *
 * A bill is a future-dated expense due in the current month, OR a
 * recurring template whose next occurrence falls in the current month.
 *
 * Returns an array of bill rows with an extra `paid` flag and a
 * `source` field ('oneoff' | 'recurring') and `parent_id` for recurring.
 */
function getUpcomingBills($pdo, $userId) {
    // If the `paid` column hasn't been added (schema not updated), the
    // upcoming-bills feature isn't available yet — return empty gracefully.
    if (!columnExists($pdo, 'expenses', 'paid')) {
        return [];
    }

    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd   = date('Y-m-t');

    $bills = [];

    // 1) One-off future-dated expenses in the current month (strictly after today)
    // Stopped recurring templates are preserved as explicit stopped rows,
    // so they must be excluded here instead of appearing as one-offs.
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS category_name, 'oneoff' AS source
        FROM expenses e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = ?
          AND e.type = 'expense'
          AND e.is_recurring = 0
          AND e.parent_id IS NULL
          AND e.recurring_end_date IS NULL
          AND e.date > ?
          AND e.date <= ?
        ORDER BY e.date ASC
    ");
    $stmt->execute([$userId, $today, $monthEnd]);
    $bills = array_merge($bills, $stmt->fetchAll());

    // 2) Recurring templates whose next occurrence lands in the current month
    $getRecurring = $pdo->prepare("
        SELECT e.*, c.name AS category_name, 'recurring' AS source
        FROM expenses e
        LEFT JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = ?
          AND e.type = 'expense'
          AND e.is_recurring = 1
        ORDER BY e.date ASC
    ");
    $getRecurring->execute([$userId]);
    $templates = $getRecurring->fetchAll();

    $stepMap = [
        'daily'   => '+1 day',
        'weekly'  => '+1 week',
        'monthly' => '+1 month',
        'yearly'  => '+1 year',
    ];

    foreach ($templates as $tpl) {
        $interval = $tpl['recurring_interval'];
        if (empty($interval) || !isset($stepMap[$interval])) {
            continue;
        }

        $startDate = new DateTime($tpl['date']);
        $nextDate  = clone $startDate;

        // If the start date is in the future (after today, this month), bill due at start date.
        // A bill due today is NOT "upcoming", so it is skipped here and handled below.
        if ($tpl['date'] > $today && $tpl['date'] <= $monthEnd) {
            $nextDate = $startDate;
        } else {
            // If the template starts today, move to the next occurrence.
            if ($tpl['date'] === $today) {
                $nextDate->modify($stepMap[$interval]);
            }
            // Advance to the first occurrence strictly after today
            while ($nextDate->format('Y-m-d') <= $today) {
                $nextDate->modify($stepMap[$interval]);
            }
        }

        $dueDate = $nextDate->format('Y-m-d');

        // Only include if the next occurrence is within the current month
        if ($dueDate >= $monthStart && $dueDate <= $monthEnd) {
            // Check whether this specific occurrence has already been generated
            // and marked paid (look for a child entry with this date).
            $paid = 0;
            $checkChild = $pdo->prepare("
                SELECT paid FROM expenses
                WHERE user_id = ? AND parent_id = ? AND date = ?
                LIMIT 1
            ");
            $checkChild->execute([$userId, $tpl['id'], $dueDate]);
            $child = $checkChild->fetch();
            if ($child) {
                $paid = (int)$child['paid'];
            }

            $bills[] = array_merge($tpl, [
                'source'       => 'recurring',
                'date'         => $dueDate,
                'billed'       => $dueDate, // occurrence date for this month
                'paid'         => $paid,
                'parent_id'    => $tpl['id'],
                'is_recurring' => 1,
            ]);
        }
    }

    // Sort by date ascending
    usort($bills, function ($a, $b) {
        return strcmp($a['date'], $b['date']);
    });

    return $bills;
}

/**
 * Resolve a remove request against the current month's upcoming-bill list.
 * Returns the widget row only when the requested id is actually present there.
 */
function getUpcomingBillRemovalTarget($pdo, $userId, $billId) {
    $upcomingBills = getUpcomingBills($pdo, $userId);

    foreach ($upcomingBills as $bill) {
        if ((int)$bill['id'] === (int)$billId) {
            return $bill;
        }
    }

    return null;
}

/** Mark a bill as paid (or unmark) for the current month. */
function markBillPaid($pdo, $userId, $billId, $paid = true) {
    // If the `paid` column hasn't been added (schema not updated), nothing to do.
    if (!columnExists($pdo, 'expenses', 'paid')) {
        return false;
    }

    // For recurring templates, we mark the child occurrence for the current month.
    // $billId is the parent template id; we find/create the child for the current month.
    $stmt = $pdo->prepare("
        SELECT * FROM expenses WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$billId, $userId]);
    $bill = $stmt->fetch();

    if (!$bill) {
        return false;
    }

    if ($bill['is_recurring']) {
        // Find the child occurrence for the current month
        $today = new DateTime('today');
        $startDate = new DateTime($bill['date']);
        $stepMap = [
            'daily'   => '+1 day',
            'weekly'  => '+1 week',
            'monthly' => '+1 month',
            'yearly'  => '+1 year',
        ];
        $interval = $bill['recurring_interval'];
        if (empty($interval) || !isset($stepMap[$interval])) {
            return false;
        }

        $today = date('Y-m-d');
        $monthEnd = date('Y-m-t');
        $nextDate = clone $startDate;

        // Match getUpcomingBills(): only a template whose start date is in the
        // future and still within this month should use that start date. If the
        // template starts today, or its next interval lands on today, advance to
        // the first occurrence strictly after today.
        if ($bill['date'] > $today && $bill['date'] <= $monthEnd) {
            $nextDate = $startDate;
        } else {
            if ($bill['date'] === $today) {
                $nextDate->modify($stepMap[$interval]);
            }
            while ($nextDate->format('Y-m-d') <= $today) {
                $nextDate->modify($stepMap[$interval]);
            }
        }

        $occurrenceDate = $nextDate->format('Y-m-d');

        // Check if a child exists for this occurrence
        $checkChild = $pdo->prepare("
            SELECT id FROM expenses WHERE user_id = ? AND parent_id = ? AND date = ?
        ");
        $checkChild->execute([$userId, $bill['id'], $occurrenceDate]);
        $child = $checkChild->fetch();

        if ($child) {
            $updateChild = $pdo->prepare("
                UPDATE expenses SET paid = ?, paid_at = ? WHERE id = ?
            ");
            $updateChild->execute([$paid ? 1 : 0, $paid ? date('Y-m-d H:i:s') : null, $child['id']]);
        } else {
            // Create a child occurrence and mark it paid
            $insertChild = $pdo->prepare("
                INSERT INTO expenses
                (user_id, category_id, amount, type, description, date, is_recurring, recurring_interval, recurring_end_date, parent_id, paid, paid_at)
                VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?, ?, ?)
            ");
            $insertChild->execute([
                $userId, $bill['category_id'], $bill['amount'], $bill['type'],
                $bill['description'], $occurrenceDate, $bill['id'],
                $paid ? 1 : 0, $paid ? date('Y-m-d H:i:s') : null
            ]);
        }
        return true;
    }

    // One-off expense: just flip the paid flag
    $updateOneOff = $pdo->prepare("
        UPDATE expenses SET paid = ?, paid_at = ? WHERE id = ? AND user_id = ?
    ");
    $updateOneOff->execute([$paid ? 1 : 0, $paid ? date('Y-m-d H:i:s') : null, $bill['id'], $userId]);
    return true;
}

