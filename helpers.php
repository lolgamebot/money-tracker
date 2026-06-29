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

function renderNav() {
    echo '
    <nav class="bg-[#111827] border-b border-slate-700 border-l-4 border-l-indigo-500 px-6 py-4 flex items-center justify-between">
        <a href="index.php" class="text-white font-bold text-lg tracking-tight">💰 Money Tracker</a>
        <div class="flex gap-6 text-sm">
            <a href="index.php" class="text-slate-400 hover:text-white transition-colors">Dashboard</a>
            <a href="add.php" class="text-slate-400 hover:text-white transition-colors">Add Record</a>
            <a href="categories.php" class="text-slate-400 hover:text-white transition-colors">Categories</a>
            <a href="profile.php" class="text-slate-400 hover:text-white transition-colors">Profile</a>
            <a href="logout.php" class="text-rose-400 hover:text-rose-300 transition-colors">Logout</a>
        </div>
    </nav>';
}
