<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($username) || empty($password)) {
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
            session_regenerate_id(true);

            $_SESSION["user_id"]  = $account["id"];
            $_SESSION["username"] = $account["username"];

            require "process_recurring.php";
            processRecurring($pdo, $account["id"]);

            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Invalid username or password!";
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
