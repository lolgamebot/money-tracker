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

    if (empty($username) || empty($password) || empty($confirm)) {
        $errors[] = "Please fill in all fields!";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long!";
    } elseif ($password !== $confirm) {
        $errors[] = "Passwords do not match!";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long!";
    } else {
        $checkUsername = $pdo->prepare("SELECT id FROM accounts WHERE username = ?");
        $checkUsername->execute([$username]);

        if ($checkUsername->fetch()) {
            $errors[] = "Username is already taken!";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $createAccount = $pdo->prepare("INSERT INTO accounts (username, password) VALUES (?, ?)");
            $createAccount->execute([$username, $hashed]);
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

        <?php renderAlerts($errors, $successes); ?>

        <form action="register.php" method="POST" class="space-y-5">
            <?php renderCsrfInput(); ?>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Username</label>
                <input type="text" name="username" value="<?= e($_POST['username'] ?? '') ?>" required class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Password</label>
                <input type="password" name="password" required minlength="6" class="<?= INPUT_CLASS ?>">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-400 mb-1">Confirm Password</label>
                <input type="password" name="confirm" required minlength="6" class="<?= INPUT_CLASS ?>">
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

<?php renderFooter(); ?>

</content>
