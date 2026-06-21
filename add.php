<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

$getCategories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
$getCategories->execute([$userId]);
$categories = $getCategories->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST["amount"];
    $categoryId = $_POST["category_id"];
    $description = $_POST["description"];
    $date = $_POST["date"];

    if (empty($amount) || empty($categoryId) || empty($date)) {
        $error = "Please fill all required fields!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount!";
    } else {
        $createExpense = $pdo->prepare("INSERT INTO expenses (user_id, category_id, amount, description, date) VALUES (?, ?, ?, ?, ?)");
        $createExpense->execute([$userId, $categoryId, $amount, $description, $date]);
        $success = "Expense added!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense</title>
</head>

<body>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add.php">Add Expense</a>
        <a href="categories.php">Categories</a>
        <a href="login.php">Logout</a>
    </nav>

    <div class="container">
        <h1>Add Expense</h1>

        <?php showMessage($error ?? null, $success ?? null); ?>

        <?php if (count($categories) === 0): ?>
            <p>You have no categories yet! <a href="categories.php">Add one here</a> before adding an expense.</p>
        <?php else: ?>
            <form action="add.php" method="POST">
                <label>Amount:</label>
                <input type="number" name="amount" step="0.01" min="0.01" required>

                <br><br>

                <label>Category:</label>
                <select name="category_id" required>
                    <option value="">--Select a category --</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category["id"] ?>"><?= $category["name"] ?></option>
                    <?php endforeach; ?>
                </select>

                <br><br>

                <label>Description: <small>(optional)</small></label>
                <input type="text" name="description" placeholder="What was this expense for?">

                <br><br>

                <label>Date:</label>
                <input type="date" name="date" required>

                <br><br>

                <button type="submit">Add Expense</button>
                <a href="index.php">Cancel</a>
            </form>
        <?php endif; ?>
    </div>

</body>

</html>