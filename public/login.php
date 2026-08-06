<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

// If the previous session timed out (inactivity), surface a friendly notice.
$sessionExpiredMessage = "";
if (!empty($_SESSION["session_expired"])) {
    $sessionExpiredMessage = "Your session expired. Please sign in again.";
    unset($_SESSION["session_expired"]);
}

$errors = [];

// --- Login rate limiting (per username AND per source IP) -----------------
$MAX_USER_ATTEMPTS = 5;
$MAX_IP_ATTEMPTS   = 12;
$LOCKOUT_TIME = 15 * 60; // 15 minutes
$isLockedOut  = false;
$lockMsg      = "";
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

// The rate-limiting feature depends on the `login_attempts` table, which
// may not exist until the schema update is applied. Gracefully skip it.
$rateLimitEnabled = columnExists($pdo, 'login_attempts', 'id');

if ($rateLimitEnabled && isset($_POST['username'])) {
    $checkUsername = trim($_POST['username']);
    if ($checkUsername !== '') {
        // Per-IP check (guards against sweeping all accounts in parallel).
        $ipStmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = ?
              AND attempted_at > (NOW() - INTERVAL 900 SECOND)
        ");
        $ipStmt->execute([$ipAddress]);
        if ((int)$ipStmt->fetchColumn() >= $MAX_IP_ATTEMPTS) {
            $isLockedOut = true;
            $lockMsg     = "Too many failed login attempts from this network. Please try again later.";
        }

        // Per-username lockout (guards a single account from brute force).
        if (!$isLockedOut) {
            $attemptStmt = $pdo->prepare("
                SELECT COUNT(*) FROM login_attempts
                WHERE username = ?
                  AND attempted_at > (NOW() - INTERVAL 900 SECOND)
            ");
            $attemptStmt->execute([$checkUsername]);
            if ((int)$attemptStmt->fetchColumn() >= $MAX_USER_ATTEMPTS) {
                $isLockedOut = true;
                $lockMsg     = "Too many failed login attempts. Please try again in 15 minutes.";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($isLockedOut) {
        $errors[] = $lockMsg ?: "Too many failed login attempts. Please try again in 15 minutes.";
    } elseif (empty($username) || empty($password)) {
        $errors[] = "Please fill in all fields!";
    } else {
        $findAccount = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $findAccount->execute([$username]);
        $account = $findAccount->fetch();

        $authenticated = false;

        if ($account) {
            if (password_verify($password, $account["password"])) {
                $authenticated = true;
                if (password_needs_rehash($account["password"], PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateHash = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
                    $updateHash->execute([$newHash, $account["id"]]);
                }
            } elseif ($account["password"] === $password) {
                // Legacy unhashed fallback: rehash immediately for security
                $authenticated = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateHash = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
                $updateHash->execute([$newHash, $account["id"]]);
            }
        }

if ($authenticated) {
            // Clear any previous failed attempt records for this username (if rate-limiting is enabled)
            if ($rateLimitEnabled) {
                $clearAttempts = $pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
                $clearAttempts->execute([$username]);
            }

            session_regenerate_id(true);
            // Rotate the CSRF token so a token captured before login can't be reused.
            unset($_SESSION["csrf_token"]);

            $_SESSION["user_id"]  = $account["id"];
            $_SESSION["username"] = $account["username"];
            $_SESSION["last_activity"] = time();

            require "process_recurring.php";
            processRecurring($pdo, $account["id"]);

            header("Location: index.php");
            exit;
        } else {
            $errCount = "Invalid username or password!";

            if ($rateLimitEnabled) {
                // Record a failed attempt
                $recordAttempt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
                $recordAttempt->execute([$username, $ipAddress]);

                // Show remaining attempts before lockout
                $attemptStmt = $pdo->prepare("
                    SELECT COUNT(*) FROM login_attempts
                    WHERE username = ? AND attempted_at > (NOW() - INTERVAL 900 SECOND)
                ");
                $attemptStmt->execute([$username]);
                $cnt = (int)$attemptStmt->fetchColumn();
                $remaining = max(0, $MAX_USER_ATTEMPTS - $cnt);
                if ($remaining > 0) {
                    $errCount .= " You have $remaining attempt(s) left before lockout.";
                } else {
                    $errCount = "Too many failed login attempts. Please try again in 15 minutes.";
                }
            }

            $errors[] = $errCount;
        }
    }
}
?>

<?php renderHeader('Login', ['bodyClass' => 'bg-[#0a0f1e] min-h-screen flex items-center justify-center text-slate-200 px-4']); ?>

    <div class="w-full max-w-md bg-[#111827] rounded-2xl p-6 sm:p-8 border border-slate-700 shadow-xl">
        <div class="mb-8 text-center">
            <div class="inline-flex p-3 rounded-full bg-indigo-500/10 border border-indigo-500/20 mb-4">
                <?= svgIcon('wallet', 'h-8 w-8 text-indigo-400') ?>
            </div>
            <h1 class="text-3xl font-bold text-white">Money Tracker</h1>
            <p class="text-slate-400 mt-1 text-sm">Sign in to your account</p>
        </div>

        <?php renderAlerts($errors, []); ?>

        <?php if ($sessionExpiredMessage !== ""): ?>
            <div class="bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 rounded-lg px-4 py-3 mb-6 text-sm flex items-center gap-2">
                <?= svgIcon('alert', 'h-4 w-4 text-indigo-400') ?>
                <?= e($sessionExpiredMessage) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
            <?php renderCsrfInput(); ?>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="username" autofocus class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="loginPassword" required autocomplete="current-password" class="<?= INPUT_CLASS ?> pr-11">
                    <button type="button" data-toggle-password="loginPassword" tabindex="-1"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors duration-200 text-sm flex items-center justify-center gap-2">
                <?= svgIcon('login') ?>
                Sign In
            </button>
        </form>

        <p class="text-center text-slate-400 text-sm mt-6">
            Don't have an account?
            <a href="register.php" class="text-indigo-400 hover:text-indigo-300 font-medium">Register here</a>
        </p>
    </div>

    <script>
        // Show/hide password toggles
        document.querySelectorAll("[data-toggle-password]").forEach(function(btn) {
            btn.addEventListener("click", function() {
                var input = document.getElementById(btn.getAttribute("data-toggle-password"));
                if (!input) return;
                var isPassword = input.type === "password";
                input.type = isPassword ? "text" : "password";
                btn.setAttribute("aria-label", isPassword ? "Hide password" : "Show password");
            });
        });
    </script>

<?php renderFooter(); ?>

</content>
