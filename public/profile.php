<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

// Fetch current account info
$getAccount = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
$getAccount->execute([$userId]);
$account = $getAccount->fetch();

$errors   = [];
$successes = [];

// Handle username change
if (isset($_POST["change_username"])) {
    verifyCsrfToken();
    $newUsername = trim($_POST["username"] ?? "");

    if (empty($newUsername)) {
        $errors[] = "Username cannot be empty!";
    } elseif (strlen($newUsername) < 3) {
        $errors[] = "Username must be at least 3 characters long!";
    } else {
        $checkUsername = $pdo->prepare("SELECT id FROM accounts WHERE username = ? AND id != ?");
        $checkUsername->execute([$newUsername, $userId]);

        if ($checkUsername->fetch()) {
            $errors[] = "Username already taken!";
        } else {
            $updateUsername = $pdo->prepare("UPDATE accounts SET username = ? WHERE id = ?");
            $updateUsername->execute([$newUsername, $userId]);
            $_SESSION["username"] = $newUsername;
            $account["username"] = $newUsername;
            $successes[] = "Username updated!";
        }
    }
}

// Handle password change
if (isset($_POST["change_password"])) {
    verifyCsrfToken();
    $currentPassword  = $_POST["current_password"] ?? "";
    $newPassword      = $_POST["new_password"] ?? "";
    $confirmPassword  = $_POST["confirm_password"] ?? "";

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errors[] = "Please fill all password fields!";
    } elseif (!password_verify($currentPassword, $account["password"])) {
        $errors[] = "Current password is incorrect!";
    } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "New passwords do not match!";
    } elseif (strlen($newPassword) < 6) {
        $errors[] = "New password must be at least 6 characters!";
    } else {
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassword = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
        $updatePassword->execute([$hashed, $userId]);
        $successes[] = "Password updated!";
    }
}
?>

<?php renderHeader('Profile'); ?>

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
            <?= svgIcon('user', 'h-7 w-7 text-indigo-400') ?>
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
                <?= svgIcon('edit', 'h-5 w-5 text-indigo-400') ?>
                Change Username
            </h2>

            <?php renderAlerts($errors, $successes); ?>

            <form action="profile.php" method="POST" class="space-y-4">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">New Username</label>
                    <input type="text" name="username" value="<?= e($account["username"]) ?>" required class="<?= INPUT_CLASS ?>">
                </div>
                <button type="submit" name="change_username"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <?= svgIcon('check') ?>
                    Update Username
                </button>
            </form>
        </div>

        <!-- Change Password -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <?= svgIcon('key', 'h-5 w-5 text-indigo-400') ?>
                Change Password
            </h2>

            <?php renderAlerts($errors, $successes); ?>

            <form action="profile.php" method="POST" class="space-y-4">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Current Password</label>
                    <input type="password" name="current_password" required class="<?= INPUT_CLASS ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
                    <input type="password" name="new_password" required class="<?= INPUT_CLASS ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required class="<?= INPUT_CLASS ?>">
                </div>
                <button type="submit" name="change_password"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <?= svgIcon('key') ?>
                    Update Password
                </button>
            </form>
        </div>
    </div>

<?php renderFooter(); ?>

</content>
