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
    $confirm = $_POST["confirm"];

    if (empty($username) || empty($password) || empty($confirm)) {
        $error = "Please fill in all fields!";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match!";
    } else {
        $checkUsername = $pdo->prepare("SELECT * FROM accounts WHERE username = ?");
        $checkUsername->execute([$username]);
        $existing = $checkUsername->fetch();

        if ($existing) {
            $error = "Username already taken!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $createAccount = $pdo->prepare("INSERT INTO accounts (username, password) VALUES (?, ?)");
            $createAccount->execute([$username, $hashed]);
            $success = "Account created! <a href='login.php' class='underline'>Login here</a>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen flex items-center justify-center text-slate-200 px-4">

    <div class="w-full max-w-md bg-[#111827] rounded-2xl p-8 border border-slate-700 shadow-xl">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-white">Create Account</h1>
            <p class="text-slate-400 mt-1 text-sm">Start tracking your money today</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-5">
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

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Confirm Password</label>
                <input type="password" name="confirm" required
                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors duration-200 mt-2">
                Create Account
            </button>
        </form>

        <p class="text-center text-slate-400 text-sm mt-6">
            Already have an account?
            <a href="login.php" class="text-indigo-400 hover:text-indigo-300 font-medium">Login here</a>
        </p>
    </div>

</body>
</html>