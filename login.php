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
    <title>Login</title>
</head>
<body>

    <div class="auth-container">
        <h1>Login</h1>

        <?php showMessage($error ?? null); ?>

        <form action="login.php" method="POST">
            <label>Username:</label>
            <input type="text" name="username" required>

            <br><br>

            <label>Password:</label>
            <input type="password" name="password" required>

            <br><br>

            <button type="submit">Login</button>
        </form>

        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
    
</body>
</html>