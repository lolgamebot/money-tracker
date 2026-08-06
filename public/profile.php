<?php
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

// Handle email change (only if the email column exists)
if (isset($_POST["change_email"]) && columnExists($pdo, 'accounts', 'email')) {
    verifyCsrfToken();
    $newEmail = trim($_POST["email"] ?? "");

    if (empty($newEmail)) {
        $errors[] = "Email cannot be empty!";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address!";
    } else {
        $updateEmail = $pdo->prepare("UPDATE accounts SET email = ? WHERE id = ?");
        $updateEmail->execute([$newEmail, $userId]);
        $account["email"] = $newEmail;
        $successes[] = "Email updated!";
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
    } elseif (passwordStrengthScore($newPassword) < 2) {
        $errors[] = "New password is too weak. Use at least 8 characters with a mix of uppercase, numbers, or symbols.";
    } else {
$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassword = $pdo->prepare("UPDATE accounts SET password = ? WHERE id = ?");
        $updatePassword->execute([$hashed, $userId]);
        $successes[] = "Password updated!";
    }
}

$modal = isset($_GET["modal"]) || isAjaxRequest();

// If this is an AJAX submit and the update succeeded, respond with JSON success.
if (isAjaxRequest() && !empty($successes) && empty($errors)) {
    $firstSuccess = (string)reset($successes);
    respondJson(['success' => true, 'message' => $firstSuccess]);
}
?>

<?php if ($modal): ?>
<title data-modal-title>My Profile</title>
<?php endif; ?>

<?php if (!$modal): ?>
<?php renderHeader('Profile'); ?>

    <?php renderNav(); ?>

<div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6 flex items-center gap-3">
            <?= svgIcon('user', 'h-7 w-7 text-indigo-400') ?>
            My Profile
        </h1>
<?php else: ?>
    <div>
<?php endif; ?>

        <!-- Account Info -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-6 mb-6">
            <div class="flex items-center gap-4 mb-4">
                <div class="w-14 h-14 rounded-full bg-indigo-600 flex items-center justify-center text-2xl font-bold text-white">
                    <?= strtoupper(substr(e($account["username"]), 0, 1)) ?>
                </div>
<div>
                    <p class="text-white font-semibold text-lg"><?= e($account["username"]) ?></p>
<p class="text-slate-400 text-sm">Member since <?= date("F Y", strtotime($account["created_at"])) ?></p>
                    <?php if (!empty($account["email"])): ?>
                        <p class="text-slate-400 text-sm mt-0.5"><?= e($account["email"]) ?></p>
                    <?php endif; ?>
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

        <?php if (columnExists($pdo, 'accounts', 'email')): ?>
        <!-- Change Email -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6 mb-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <?= svgIcon('edit', 'h-5 w-5 text-indigo-400') ?>
                Email Address
            </h2>

            <form action="profile.php" method="POST" class="space-y-4">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Email</label>
                    <input type="email" name="email" value="<?= e($account['email'] ?? '') ?>" class="<?= INPUT_CLASS ?>">
                </div>
                <button type="submit" name="change_email"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <?= svgIcon('check') ?>
                    Update Email
                </button>
            </form>
        </div>
        <?php endif; ?>

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
                    <div class="relative">
                        <input type="password" name="current_password" id="currentPassword" required autocomplete="current-password" class="<?= INPUT_CLASS ?> pr-11">
                        <button type="button" data-toggle-password="currentPassword" tabindex="-1"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">New Password</label>
                    <div class="relative">
                        <input type="password" name="new_password" id="newPassword" required minlength="8" autocomplete="new-password" class="<?= INPUT_CLASS ?> pr-11">
                        <button type="button" data-toggle-password="newPassword" tabindex="-1"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                    <div class="mt-2 hidden" id="strengthMeter">
                        <div class="h-1.5 w-full bg-slate-700 rounded-full overflow-hidden">
                            <div id="strengthBar" class="h-full transition-all duration-300" style="width: 0%;"></div>
                        </div>
                        <p id="strengthLabel" class="text-xs mt-1 text-slate-400"></p>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" name="confirm_password" id="confirmPassword" required autocomplete="new-password" class="<?= INPUT_CLASS ?> pr-11">
                        <button type="button" data-toggle-password="confirmPassword" tabindex="-1"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" name="change_password"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2">
                    <?= svgIcon('key') ?>
                    Update Password
                </button>
            </form>
        </div>
    </div>

    <script>
        // Password strength meter
        const newPassword = document.getElementById('newPassword');
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthBar = document.getElementById('strengthBar');
        const strengthLabel = document.getElementById('strengthLabel');

        if (newPassword) {
            newPassword.addEventListener('input', function() {
                const val = this.value;
                if (val.length === 0) {
                    strengthMeter.classList.add('hidden');
                    return;
                }
                strengthMeter.classList.remove('hidden');

                let score = 0;
                if (val.length >= 8) score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;

                const configs = [
                    { w: '0%',   c: '#f43f5e', l: 'Too weak', t: 'text-rose-400' },
                    { w: '30%',  c: '#f43f5e', l: 'Weak', t: 'text-rose-400' },
                    { w: '55%',  c: '#f59e0b', l: 'Fair', t: 'text-amber-400' },
                    { w: '80%',  c: '#10b981', l: 'Good', t: 'text-emerald-400' },
                    { w: '100%', c: '#10b981', l: 'Strong', t: 'text-emerald-400' }
                ];
                const cfg = configs[score];
                strengthBar.style.width = cfg.w;
                strengthBar.style.backgroundColor = cfg.c;
                strengthLabel.textContent = cfg.l;
                strengthLabel.className = 'text-xs mt-1 ' + cfg.t;
            });
        }

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

<?php if (!$modal): ?>
<?php renderFooter(); ?>
<?php endif; ?>
