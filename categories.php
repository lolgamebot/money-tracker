<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

if (isset($_GET["delete"])) {
    $categoryId = $_GET["delete"];
    $deleteCategory = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $deleteCategory->execute([$categoryId, $userId]);
    header("Location: categories.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $categoryName = $_POST["name"];

    if (empty($categoryName)) {
        $error = "Please enter a category name!";
    } else {
        $checkCategory = $pdo->prepare("SELECT * FROM categories WHERE name = ? AND user_id = ?");
        $checkCategory->execute([$categoryName, $userId]);
        $existingCategory = $checkCategory->fetch();

        if ($existingCategory) {
            $error = "Category already exists!";
        } else {
            $createCategory = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
            $createCategory->execute([$userId, $categoryName]);
            $success = "Category added!";
        }
    }
}

$getCategories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
$getCategories->execute([$userId]);
$categories = $getCategories->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">My Categories</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $success ?></div>
        <?php endif; ?>

        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6 mb-6">
            <form action="categories.php" method="POST" class="flex gap-3">
                <input type="text" name="name" placeholder="e.g. Food, Transport..." required
                    class="flex-1 bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                <button type="submit"
                    class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors">
                    Add
                </button>
            </form>
        </div>

        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <?php if (count($categories) === 0): ?>
                <p class="text-slate-400 text-sm">No categories yet. Add one above!</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($categories as $category): ?>
                        <div class="flex items-center justify-between py-2.5 border-b border-slate-800 last:border-0">
                            <span class="text-slate-200"><?= $category["name"] ?></span>
                            <a href="categories.php?delete=<?= $category["id"] ?>"
                               onclick="return confirm('Delete this category?')"
                               class="text-rose-400 hover:text-rose-300 text-sm transition-colors">Delete</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>