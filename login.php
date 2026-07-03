<?php
session_start();
require "db.php";
require "helpers.php";

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields!";
    } else {
        $findAccount = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $findAccount->execute([$username]);
        $account = $findAccount->fetch();

        if ($account && password_verify($password, $account["password"])) {
            $_SESSION["user_id"] = $account["id"];
            $_SESSION["username"] = $account["username"];

            require "process_recurring.php";
            processRecurring($pdo, $account["id"]);

            header("Location: index.php");
            exit;
        } else {
            $error = "Wrong username or password!";
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

    <div class="w-full max-w-md bg-[#111827] rounded-2xl p-8 border border-slate-700 shadow-xl">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-white">Money Tracker</h1>
            <p class="text-slate-400 mt-1 text-sm">Sign in to your account</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" required
                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <input type="password" name="password" required
                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors duration-200">
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