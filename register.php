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
            $success = "Account created! <a href='login.php'>Login here</a>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
</head>
<body>

    <div class="auth-container">
        <h1>Create Account</h1>

        <?php showMessage($error ?? null, $success ?? null); ?>

        <form action="register.php" method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>

            <br><br>

            <label>Password:</label>
            <input type="password" name="password" required>

            <br><br>

            <label>Confirm Password</label>
            <input type="password" name="confirm" required>

            <br><br>
            
            <button type="submit">Register</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
    
</body>
</html>