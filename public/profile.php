<?php
session_start();
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

// Fetch current account info
$getAccount = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$getAccount->execute([$userId]);
$account = $getAccount->fetch();

// Handle username change
if (isset($_POST["change_username"])) {
    verifyCsrfToken();
    $newUsername = trim($_POST["username"] ?? "");

    if (empty($newUsername)) {
        $errorUsername = "Username cannot be empty!";
    } elseif (strlen($newUsername) < 3) {
        $errorUsername = "Username must be at least 3 characters long!";
    } else {
        $checkUsername = $pdo->prepare("SELECT * FROM accounts WHERE username = ? AND id != ?");
        $checkUsername->execute([$newUsername, $userId]);
        $existing = $checkUsername->fetch();

        if ($existing) {
            $errorUsername = "Username already taken!";
        } else {
            $updateUsername = $pdo->prepare("UPDATE accounts SET username = ? WHERE id = ?");
            $updateUsername->execute([$newUsername, $userId]);
            $_SESSION["username"] = $newUsername;
            $successUsername = "Username updated!";
            $account["username"] = $newUsername;
        }
    }
}

// Handle password change
if (isset($_POST["change_password"])) {
    verifyCsrfToken();
    $currentPassword = $_POST["current_password"] ?? "";
    $newPassword = $_POST["new_password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorPassword = "Please fill all password fields!";
    } elseif (!password_verify($currentPassword, $account["password"])) {
        $errorPassword = "Current password is incorrect!";
    } elseif ($newPassword !== $confirmPassword) {
        $errorPassword = "New passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $errorPassword = "New password must be at least 6 characters!";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassword = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
        $updatePassword->execute([$hashed, $userId]);
        $successPassword = "Password updated!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            My Profile
        </h1>

        <!-- Account Info -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-6 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center text-2xl font-bold text-white">
                    <?= strtoupper(substr(e($account["username"]), 0, 1)) ?>
                </div>
                <div>
                    <p class="text-white font-semibold text-lg"><?= e($account["username"]) ?></p>
                    <p class="text-slate-400 text-sm">Member since <?= date("F Y", strtotime($account["created_at"])) ?></p>
                </div>
            </div>
        </div>

        <!-- Change Username -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Change Username
            </h2>

            <?php if (isset($errorUsername)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-4 text-sm"><?= e($errorUsername) ?></div>
            <?php endif; ?>

            <?php if (isset($successUsername)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-4 text-sm"><?= e($successUsername) ?></div>
            <?php endif; ?>

            <form action="profile.php" method="POST" class="space-y-4">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">New Username</label>
                    <input type="text" name="username" value="<?= e($account["username"]) ?>" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <button type="submit" name="change_username"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Update Username
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                </svg>
                Change Password
            </h2>

            <?php if (isset($errorPassword)): ?>
                <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-4 text-sm"><?= e($errorPassword) ?></div>
            <?php endif; ?>

            <?php if (isset($successPassword)): ?>
                <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-4 text-sm"><?= e($successPassword) ?></div>
            <?php endif; ?>

            <form action="profile.php" method="POST" class="space-y-4">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Current Password</label>
                    <input type="password" name="current_password" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
                    <input type="password" name="new_password" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>
                <button type="submit" name="change_password"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    Update Password
                </button>
            </form>
        </div>
    </div>

</body>

</html>