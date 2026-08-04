<?php
session_start();
require "config/db.php";
require "includes/helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

// Handle delete (CSRF-protected)
if (isset($_GET["delete"])) {
    verifyCsrfGet();
    $categoryId = (int)$_GET["delete"];
    $deleteCategory = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $deleteCategory->execute([$categoryId, $userId]);
    header("Location: categories.php");
    exit;
}

// Handle rename
if (isset($_GET["edit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();
    $categoryId = (int)$_GET["edit"];
    $newName = trim($_POST["name"] ?? "");

    if (empty($newName)) {
        $error = "Category name cannot be empty!";
    } else {
        $checkDuplicate = $pdo->prepare("SELECT * FROM categories WHERE name = ? AND user_id = ? AND id != ?");
        $checkDuplicate->execute([$newName, $userId, $categoryId]);
        $duplicate = $checkDuplicate->fetch();

        if ($duplicate) {
            $error = "Category name already exists!";
        } else {
            $updateCategory = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
            $updateCategory->execute([$newName, $categoryId, $userId]);
            $success = "Category renamed!";
        }
    }
}

// Handle add
if (!isset($_GET["edit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();
    $categoryName = trim($_POST["name"] ?? "");

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

// Fetch category being edited if any
$editingCategory = null;
if (isset($_GET["edit"])) {
    $getEditing = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
    $getEditing->execute([$_GET["edit"], $userId]);
    $editingCategory = $getEditing->fetch();
}
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

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6">My Categories</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= e($success) ?></div>
        <?php endif; ?>

        <!-- Add or Edit Form -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-6 mb-6">
            <?php if ($editingCategory): ?>
                <p class="text-sm text-slate-400 mb-3">Renaming: <span class="text-white font-medium"><?= e($editingCategory["name"]) ?></span></p>
                <form action="categories.php?edit=<?= (int)$editingCategory["id"] ?>" method="POST" class="flex flex-col sm:flex-row gap-3">
                    <?php renderCsrfInput(); ?>
                    <input type="text" name="name" value="<?= e($editingCategory["name"]) ?>" required
                        class="flex-1 bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 text-sm">
                    <div class="flex gap-2">
                        <button type="submit"
                            class="flex-1 sm:flex-initial bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Save
                        </button>
                        <a href="categories.php"
                            class="flex-1 sm:flex-initial text-center bg-slate-700 hover:bg-slate-600 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm">
                            Cancel
                        </a>
                    </div>
                </form>
            <?php else: ?>
                <form action="categories.php" method="POST" class="flex flex-col sm:flex-row gap-3">
                    <?php renderCsrfInput(); ?>
                    <input type="text" name="name" placeholder="e.g. Food, Salary..." required
                        class="flex-1 bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 text-sm">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Category
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Category List -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <?php if (count($categories) === 0): ?>
                <p class="text-slate-400 text-sm">No categories yet. Add one above!</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($categories as $category): ?>
                        <div class="flex items-center justify-between py-2.5 border-b border-slate-800 last:border-0">
                            <span class="text-slate-200"><?= e($category["name"]) ?></span>
                            <div class="flex gap-3">
                                <a href="categories.php?edit=<?= (int)$category["id"] ?>"
                                   class="text-indigo-400 hover:text-indigo-300 text-sm transition-colors flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Rename
                                </a>
                                <a href="categories.php?delete=<?= (int)$category["id"] ?><?= getCsrfQueryParam() ?>"
                                   onclick="return confirm('Delete this category?')"
                                   class="text-rose-400 hover:text-rose-300 text-sm transition-colors flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>