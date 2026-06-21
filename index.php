<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$user_id = $_SESSION["user_id"];

// Get all expenses with category name joined
$getExpenses = $pdo->prepare("
    SELECT expenses.*, categories.name AS category_name 
    FROM expenses 
    LEFT JOIN categories ON expenses.category_id = categories.id 
    WHERE expenses.user_id = ? 
    ORDER BY expenses.date DESC
");

$getExpenses->execute([$user_id]);
$expenses = $getExpenses->fetchAll();

// Total of all expenses
$getTotal = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ?");
$getTotal->execute([$user_id]);
$total = $getTotal->fetch()["total"] ?? 0;

// Total per category
$getCategoryTotals = $pdo->prepare("
    SELECT categories.name, SUM(expenses.amount) AS total
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ?
    GROUP BY categories.name
    ORDER BY total DESC
");
$getCategoryTotals->execute([$user_id]);
$categoryTotals = $getCategoryTotals->fetchAll();

// This month's total
$getMonthTotal = $pdo->prepare("
    SELECT SUM(amount) AS total 
    FROM expenses 
    WHERE user_id = ? 
    AND MONTH(date) = MONTH(CURRENT_DATE()) 
    AND YEAR(date) = YEAR(CURRENT_DATE())
");
$getMonthTotal->execute([$user_id]);
$monthTotal = $getMonthTotal->fetch()["total"] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>

<body>

    <nav>
        <a href="index.php">Dashboard</a>
        <a href="add.php">Add Expense</a>
        <a href="categories.php">Categories</a>
        <a href="logout.php">Logout</a>
    </nav>

    <div class="container">
        <h1>Welcome, <?= $_SESSION["username"] ?>!</h1>

        <!-- Summary Cards -->
        <div class="cards">
            <div class="card">
                <h3>Total Expenses</h3>
                <p class="amount">₱<?= number_format($total, 2) ?></p>
            </div>
            <div class="card">
                <h3>This Month</h3>
                <p class="amount">₱<?= number_format($monthTotal, 2) ?></p>
            </div>
            <div class="card">
                <h3>Total Records</h3>
                <p class="amount"><?= count($expenses) ?></p>
            </div>
        </div>

        <!-- Category Breakdown -->
        <h2>Spending by Category</h2>
        <?php if (count($categoryTotals) === 0): ?>
            <p>No data yet.</p>
        <?php else: ?>
            <table border="1" cellpadding="10">
                <tr>
                    <th>Category</th>
                    <th>Total</th>
                </tr>
                <?php foreach ($categoryTotals as $cat): ?>
                    <tr>
                        <td><?= $cat["name"] ?></td>
                        <td>₱<?= number_format($cat["total"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

        <!-- Expense List -->
        <h2>All Expenses</h2>
        <a href="add.php">Add New Expense</a>
        <br><br>

        <?php if (count($expenses) === 0): ?>
            <p>No expenses yet! <a href="add.php">Add your first one</a>.</p>
        <?php else: ?>
            <table border="1" cellpadding="10">
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?= $expense["date"] ?></td>
                        <td><?= $expense["category_name"] ?></td>
                        <td><?= $expense["description"] ?: "-" ?></td>
                        <td>₱<?= number_format($expense["amount"], 2) ?></td>
                        <td>
                            <a href="edit.php?id=<?= $expense["id"] ?>">Edit</a>
                            &nbsp;
                            <a href="delete.php?id=<?= $expense["id"] ?>" onclick="return confirm('Delete this expense?')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>

    </div>

</body>

</html>