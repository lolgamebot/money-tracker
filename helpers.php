<?php
function showMessage($error = null, $success = null)
{
    if (isset($error)) {
        echo "<p class='error'>" . $error . "</p>";
    }
    if (isset($success)) {
        echo "<p class='success'>" . $success . "</p>";
    }
}

function requireLogin()
{
    if (!isset($_SESSION["user_id"])) {
        header("Location: login.php");
        exit;
    }
}
