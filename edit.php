<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

if (!isset($_GET["id"])) {
    header("Location: edit.php");
    exit;
}

$expenseId = $_GET["id"];

$getExpense = $pdo->prepare("SELECT * FROM categories WHERE id = ? AND user_id = ?");
$getExpense->execute([$expenseId, $userId]);
$expense = $getExpense->fetch();

if (!$expense) {
    header("Location: index.php");
    exit;
}

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
        $updateExpense = $pdo->prepare("UPDATE expenses SET amount=?, category_id=?, description=?, date=? WHERE id=? AND user_id=?");
        $updateExpense->execute([$amount, $categoryId, $description, $date, $expenseId, $userId]);

        header("Location: index.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Expense</title>
</head>

<body>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add.php">Add Expense</a>
        <a href="categories.php">Categories</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="container">
        <h1>Edit Expense</h1>

        <?php showMessage($error ?? null); ?>

        <form action="edit.php?id=<?= $expenseId ?>" method="POST">
            <label>Amount:</label>
            <input type="number" name="amount" step="0.01" min="0.01" value="<?= $expense['amount'] ?>" required>

            <br><br>

            <label>Category:</label>
            <select name="category_id" required>
                <option value="">-- Select a category --</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category["id"] ?>" <?= $category["id"] == $expense["category_id"] ? "selected" : "" ?>><?= $category["name"] ?></option>
                <?php endforeach; ?>
            </select>

            <br><br>

            <label>Description: <small>(optional)</small></label>
            <input type="text" name="description" value="<?= $expense['description'] ?>" placeholder="What was this expense for?">

            <br><br>

            <label>Date:</label>
            <input type="date" name="date" value="<?= $expense['date'] ?>" required>

            <br><br>

            <button type="submit">Update Expense</button>
            <a href="index.php">Cancel</a>
        </form>
    </div>

</body>

</html>