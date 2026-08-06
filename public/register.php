<?php
require "../config/db.php";
require "../includes/helpers.php";

initSecureSession();

if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$errors    = [];
$successes = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

$username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";
    $email    = trim($_POST["email"] ?? "");

    if (empty($username) || empty($password) || empty($confirm)) {
        $errors[] = "Please fill in all fields!";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long!";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match!";
    } elseif (passwordStrengthScore($password) < 2) {
        $errors[] = "Password is too weak. Use at least 8 characters with a mix of uppercase, numbers, or symbols.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address!";
    } else {
        $checkUsername = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
        $checkUsername->execute([$username]);

        if ($checkUsername->fetch()) {
            $errors[] = "Username is already taken!";
} else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            // Only include the email column if it exists (schema may not be updated yet)
            if (columnExists($pdo, 'accounts', 'email')) {
                $email = $email !== '' ? $email : null;
                $createAccount = $pdo->prepare("INSERT INTO accounts (username, password, email) VALUES (?, ?, ?)");
                $createAccount->execute([$username, $hashed, $email]);
            } else {
                $createAccount = $pdo->prepare("INSERT INTO accounts (username, password) VALUES (?, ?)");
                $createAccount->execute([$username, $hashed]);
            }
            $successes[] = "Account created successfully! <a href='login.php' class='underline font-semibold'>Login here</a>";
        }
    }
}
?>

<?php renderHeader('Register', ['bodyClass' => 'bg-[#0a0f1e] min-h-screen flex items-center justify-center text-slate-200 px-4 py-8']); ?>

    <div class="w-full max-w-md bg-[#111827] rounded-2xl p-6 sm:p-8 border border-slate-700 shadow-xl">
        <div class="mb-8 text-center">
            <div class="inline-flex p-3 rounded-full bg-indigo-500/10 border border-indigo-500/20 mb-4">
                <?= svgIcon('user-add', 'h-8 w-8 text-indigo-400') ?>
            </div>
            <h1 class="text-3xl font-bold text-white">Create Account</h1>
            <p class="text-slate-400 mt-1 text-sm">Start tracking your money today</p>
        </div>

        <?php renderTrustedAlerts($errors, $successes); ?>

        <form action="register.php" method="POST" class="space-y-5">
            <?php renderCsrfInput(); ?>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="username" autofocus class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Email <span class="text-slate-500">(optional)</span></label>
                <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <div class="relative">
                    <input type="password" name="password" id="regPassword" required minlength="8" autocomplete="new-password" class="<?= INPUT_CLASS ?> pr-11">
                    <button type="button" data-toggle-password="regPassword" tabindex="-1"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                </div>
                <!-- Password strength meter -->
                <div class="mt-2 hidden" id="strengthMeter">
                    <div class="h-1.5 w-full bg-slate-700 rounded-full overflow-hidden">
                        <div id="strengthBar" class="h-full transition-all duration-300" style="width: 0%;"></div>
                    </div>
                    <p id="strengthLabel" class="text-xs mt-1 text-slate-400"></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" name="confirm" id="regConfirm" required minlength="8" autocomplete="new-password" class="<?= INPUT_CLASS ?> pr-11">
                    <button type="button" data-toggle-password="regConfirm" tabindex="-1"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white transition-colors" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors duration-200 mt-2 text-sm flex items-center justify-center gap-2">
                <?= svgIcon('user-add') ?>
                Create Account
            </button>
        </form>

        <p class="text-center text-slate-400 text-sm mt-6">
            Already have an account?
            <a href="login.php" class="text-indigo-400 hover:text-indigo-300 font-medium">Login here</a>
        </p>
    </div>

    <script>
        // Password strength meter
        const regPassword = document.getElementById('regPassword');
        const strengthMeter = document.getElementById('strengthMeter');
        const strengthBar = document.getElementById('strengthBar');
        const strengthLabel = document.getElementById('strengthLabel');

        if (regPassword) {
            regPassword.addEventListener('input', function() {
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

<?php renderFooter(); ?>

</content>
