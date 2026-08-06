<?php
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

$errors    = [];
$successes = [];

// Handle delete (CSRF-protected)
if (isset($_GET["delete"])) {
    verifyCsrfGet();
    $categoryId = (int)$_GET["delete"];
    $deleteCategory = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $deleteCategory->execute([$categoryId, $userId]);
    if (isAjaxRequest()) {
        respondJson(['success' => true, 'message' => 'Category deleted!', 'reset' => 'categories.php']);
    }
    setFlash("Category deleted!");
    header("Location: categories.php");
    exit;
}

// Handle rename
if (isset($_GET["edit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();
    $categoryId = (int)$_GET["edit"];
    $newName = trim($_POST["name"] ?? "");

    if (empty($newName)) {
        $errors[] = "Category name cannot be empty!";
    } else {
        $checkDuplicate = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ? AND id != ?");
        $checkDuplicate->execute([$newName, $userId, $categoryId]);

        if ($checkDuplicate->fetch()) {
            $errors[] = "Category name already exists!";
        } else {
$updateCategory = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
$updateCategory->execute([$newName, $categoryId, $userId]);
            if (isAjaxRequest()) {
                respondJson(['success' => true, 'message' => 'Category renamed!', 'reset' => 'categories.php']);
            }
            setFlash("Category renamed!");
            header("Location: categories.php");
            exit;
        }
    }
}

// Handle add
if (!isset($_GET["edit"]) && $_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();
    $categoryName = trim($_POST["name"] ?? "");

    if (empty($categoryName)) {
        $errors[] = "Please enter a category name!";
    } else {
        $checkCategory = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ?");
        $checkCategory->execute([$categoryName, $userId]);

        if ($checkCategory->fetch()) {
            $errors[] = "Category already exists!";
        } else {
$createCategory = $pdo->prepare("INSERT INTO categories (user_id, name) VALUES (?, ?)");
$createCategory->execute([$userId, $categoryName]);
            if (isAjaxRequest()) {
                respondJson(['success' => true, 'message' => 'Category added!', 'reset' => 'categories.php']);
            }
            setFlash("Category added!");
            header("Location: categories.php");
            exit;
        }
    }
}

$categories = getCategories($pdo, $userId);

// Fetch category being edited if any
$editingCategory = null;
if (isset($_GET["edit"])) {
    $getEditing = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
    $getEditing->execute([$_GET["edit"], $userId]);
    $editingCategory = $getEditing->fetch();
    if (!$editingCategory) {
        header("Location: categories.php");
        exit;
    }
}
?>

<?php $modal = isset($_GET["modal"]) || isAjaxRequest(); ?>

<?php if ($modal): ?>
<title data-modal-title>My Categories</title>
<?php endif; ?>

<?php if (!$modal): ?>
<?php renderHeader('Categories'); ?>

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6">My Categories</h1>
<?php else: ?>
    <div>
<?php endif; ?>

        <?php renderAlerts($errors, $successes); ?>
        <?php renderFlash(); ?>

        <!-- Add or Edit Form -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-6 mb-6">
            <?php if ($editingCategory): ?>
                <p class="text-sm text-slate-400 mb-3">Renaming: <span class="text-white font-medium"><?= e($editingCategory["name"]) ?></span></p>
                <form action="categories.php?edit=<?= (int)$editingCategory["id"] ?>" method="POST" class="flex flex-col sm:flex-row gap-3">
                    <?php renderCsrfInput(); ?>
                    <input type="text" name="name" value="<?= e($editingCategory["name"]) ?>" required class="<?= INPUT_CLASS ?>">
                    <div class="flex gap-2">
                        <button type="submit"
                            class="flex-1 sm:flex-initial bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-5 py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                            <?= svgIcon('check') ?>
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
                    <input type="text" name="name" placeholder="e.g. Food, Salary..." required class="<?= INPUT_CLASS ?>">
                    <button type="submit"
                        class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors text-sm flex items-center justify-center gap-2">
                        <?= svgIcon('plus') ?>
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
                                    <?= svgIcon('edit', 'h-3.5 w-3.5') ?>
                                    Rename
                                </a>
                                <a href="categories.php?delete=<?= (int)$category["id"] ?><?= getCsrfQueryParam() ?>"
                                   onclick="return confirm('Delete this category?')"
                                   class="text-rose-400 hover:text-rose-300 text-sm transition-colors flex items-center gap-1.5">
                                    <?= svgIcon('trash', 'h-3.5 w-3.5') ?>
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php if (!$modal): ?>
<?php renderFooter(); ?>
<?php endif; ?>
