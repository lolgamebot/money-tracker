<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$errors = [];

// --- Login rate limiting (per username) --------------------------------
$MAX_ATTEMPTS = 5;
$LOCKOUT_TIME = 15 * 60; // 15 minutes
$isLockedOut  = false;
$lockRemaining = 0;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';

// The rate-limiting feature depends on the `login_attempts` table, which
// may not exist until the schema update is applied. Gracefully skip it.
$rateLimitEnabled = columnExists($pdo, 'login_attempts', 'id');

if ($rateLimitEnabled && isset($_POST['username'])) {
    $checkUsername = trim($_POST['username']);
    if ($checkUsername !== '') {
        $attemptStmt = $pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE username = ?
              AND attempted_at > (NOW() - INTERVAL 900 SECOND)
        ");
        $attemptStmt->execute([$checkUsername]);
        $failCount = (int)$attemptStmt->fetchColumn();

        if ($failCount >= $MAX_ATTEMPTS) {
            $isLockedOut = true;
            $lockRemaining = $LOCKOUT_TIME;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($isLockedOut) {
        $errors[] = "Too many failed login attempts. Please try again in 15 minutes.";
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
                $remaining = max(0, $MAX_ATTEMPTS - $cnt);
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

        <form action="login.php" method="POST" class="space-y-5">
            <?php renderCsrfInput(); ?>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" required class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <input type="password" name="password" required class="<?= INPUT_CLASS ?>">
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

<?php renderFooter(); ?>

</content>
