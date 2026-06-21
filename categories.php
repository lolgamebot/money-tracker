<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

if (isset($_GET["delete"])) {
    $categoryId = $_GET["delete"];
    $userId = $_SESSION["user_id"];

    $deleteCategory = $pdo->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $deleteCategory->execute([$categoryId, $userId]);

    header("Location: categories.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $categoryName = $_POST["name"];
    $userId = $_SESSION["user_id"];

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
$getCategories->execute([$_SESSION["user_id"]]);
$categories = $getCategories->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories</title>
</head>

<body>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add.php">Add Expense</a>
        <a href="categories.php">Categories</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="container">
        <h1>My Categories</h1>

        <?php showMessage($error ?? null, $success ?? null); ?>

        <form action="categories.php" method="POST">
            <label>Category Name:</label>
            <input type="text" name="name" placeholder="e.g. Food, Transport..." required>
            <button type="submit">Add Category</button>
        </form>

        <br>

        <?php if (count($categories) === 0): ?>
            <p>No categories yet! Add one above.</p>
        <?php else: ?>
            <table border="1" cellpadding="10">
                <tr>
                    <th>Category</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= $category["name"] ?></td>
                        <td>
                            <a href="categories.php?delete=<?= $category["id"] ?>" onclick="return confirm('Delete this category?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

</body>

</html>