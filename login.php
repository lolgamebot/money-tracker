<?php
require "db.php";
require "helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields!";
    } else {
        $findAccount = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $findAccount->execute([$username]);
        $account = $findAccount->fetch();

        $authenticated = false;

        if ($account) {
            // Check password using password_verify
            if (password_verify($password, $account["password"])) {
                $authenticated = true;
                // Rehash if algorithm/cost settings updated
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
            // Regenerate session ID to prevent Session Fixation attacks
            session_regenerate_id(true);

            $_SESSION["user_id"] = $account["id"];
            $_SESSION["username"] = $account["username"];

            require "process_recurring.php";
            processRecurring($pdo, $account["id"]);

            header("Location: index.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen flex items-center justify-center text-slate-200 px-4">

    <div class="w-full max-w-md bg-[#111827] rounded-2xl p-6 sm:p-8 border border-slate-700 shadow-xl">
        <div class="mb-8 text-center">
            <div class="inline-flex p-3 rounded-full bg-indigo-500/10 border border-indigo-500/20 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 013 6v3"/></svg>
            </div>
            <h1 class="text-3xl font-bold text-white">Money Tracker</h1>
            <p class="text-slate-400 mt-1 text-sm">Sign in to your account</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
            <?php renderCsrfInput(); ?>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" required
                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors duration-200 text-sm flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                Sign In
            </button>
        </form>

        <p class="text-center text-slate-400 text-sm mt-6">
            Don't have an account?
            <a href="register.php" class="text-indigo-400 hover:text-indigo-300 font-medium">Register here</a>
        </p>
    </div>

</body>
</html>