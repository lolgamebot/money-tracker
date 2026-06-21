<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

$getExpenses = $pdo->prepare("
    SELECT expenses.*, categories.name AS category_name 
    FROM expenses 
    LEFT JOIN categories ON expenses.category_id = categories.id 
    WHERE expenses.user_id = ? 
    ORDER BY expenses.date DESC
");
$getExpenses->execute([$userId]);
$expenses = $getExpenses->fetchAll();

$getTotal = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ?");
$getTotal->execute([$userId]);
$total = $getTotal->fetch()["total"] ?? 0;

$getCategoryTotals = $pdo->prepare("
    SELECT categories.name, SUM(expenses.amount) AS total
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ?
    GROUP BY categories.name
    ORDER BY total DESC
");
$getCategoryTotals->execute([$userId]);
$categoryTotals = $getCategoryTotals->fetchAll();

$getMonthTotal = $pdo->prepare("
    SELECT SUM(amount) AS total 
    FROM expenses 
    WHERE user_id = ? 
    AND MONTH(date) = MONTH(CURRENT_DATE()) 
    AND YEAR(date) = YEAR(CURRENT_DATE())
");
$getMonthTotal->execute([$userId]);
$monthTotal = $getMonthTotal->fetch()["total"] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Welcome back, <?= $_SESSION["username"] ?>!</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">Total Expenses</p>
                <p class="text-3xl font-bold text-white">₱<?= number_format($total, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">This Month</p>
                <p class="text-3xl font-bold text-indigo-400">₱<?= number_format($monthTotal, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">Total Records</p>
                <p class="text-3xl font-bold text-white"><?= count($expenses) ?></p>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-5 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Spending by Category</h2>
            <?php if (count($categoryTotals) === 0): ?>
                <p class="text-slate-400 text-sm">No data yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($categoryTotals as $cat): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                            <span class="text-slate-300"><?= $cat["name"] ?></span>
                            <span class="text-emerald-400 font-semibold">₱<?= number_format($cat["total"], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Expense List -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">All Expenses</h2>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Add Expense</a>
            </div>

            <?php if (count($expenses) === 0): ?>
                <p class="text-slate-400 text-sm">No expenses yet! <a href="add.php" class="text-indigo-400 hover:underline">Add your first one</a>.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-700">
                                <th class="text-left py-2 pr-4">Date</th>
                                <th class="text-left py-2 pr-4">Category</th>
                                <th class="text-left py-2 pr-4">Description</th>
                                <th class="text-right py-2 pr-4">Amount</th>
                                <th class="text-right py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr class="border-b border-slate-800 hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 pr-4 text-slate-400"><?= $expense["date"] ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2 py-1 rounded-full">
                                            <?= $expense["category_name"] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-300"><?= $expense["description"] ?: "—" ?></td>
                                    <td class="py-3 pr-4 text-right text-emerald-400 font-semibold">₱<?= number_format($expense["amount"], 2) ?></td>
                                    <td class="py-3 text-right">
                                        <a href="edit.php?id=<?= $expense["id"] ?>" class="text-slate-400 hover:text-white mr-3 transition-colors">Edit</a>
                                        <a href="delete.php?id=<?= $expense["id"] ?>" 
                                           onclick="return confirm('Delete this expense?')"
                                           class="text-rose-400 hover:text-rose-300 transition-colors">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>