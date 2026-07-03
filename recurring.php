<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

// Handle cancel recurring
if (isset($_GET["cancel"])) {
    $cancelId = $_GET["cancel"];
    $cancelRecurring = $pdo->prepare("UPDATE expenses SET is_recurring = 0, recurring_end_date = CURRENT_DATE() WHERE id = ? AND user_id = ?");
    $cancelRecurring->execute([$cancelId, $userId]);
    header("Location: recurring.php");
    exit;
}

$getRecurring = $pdo->prepare("
    SELECT expenses.*, categories.name AS category_name
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ? AND expenses.is_recurring = 1
    ORDER BY expenses.date DESC
");
$getRecurring->execute([$userId]);
$recurringRecords = $getRecurring->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Recurring Records</h1>

        <?php if (count($recurringRecords) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-10 text-center">
                <p class="text-slate-400 mb-3">No recurring records yet.</p>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Add a recurring record</a>
            </div>
        <?php else: ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-5">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-700">
                                <th class="text-left py-2 pr-4">Description</th>
                                <th class="text-left py-2 pr-4">Type</th>
                                <th class="text-left py-2 pr-4">Category</th>
                                <th class="text-left py-2 pr-4">Amount</th>
                                <th class="text-left py-2 pr-4">Repeats</th>
                                <th class="text-left py-2 pr-4">Started</th>
                                <th class="text-left py-2 pr-4">Ends</th>
                                <th class="text-right py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recurringRecords as $record): ?>
                                <tr class="border-b border-slate-800 hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 pr-4 text-slate-300"><?= $record["description"] ?: "—" ?></td>
                                    <td class="py-3 pr-4">
                                        <span class="text-xs px-2 py-1 rounded-full <?= $record['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                            <?= ucfirst($record['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2 py-1 rounded-full">
                                            <?= $record["category_name"] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 font-semibold <?= $record['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= $record['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($record["amount"], 2) ?>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-300 capitalize"><?= $record["recurring_interval"] ?></td>
                                    <td class="py-3 pr-4 text-slate-400"><?= $record["date"] ?></td>
                                    <td class="py-3 pr-4 text-slate-400">
                                        <?= $record["recurring_end_date"] ? $record["recurring_end_date"] : '<span class="text-indigo-400">Infinite</span>' ?>
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="edit.php?id=<?= $record["id"] ?>" class="text-slate-400 hover:text-white mr-3 transition-colors">Edit</a>
                                        <a href="recurring.php?cancel=<?= $record["id"] ?>"
                                           onclick="return confirm('Cancel this recurring record?')"
                                           class="text-rose-400 hover:text-rose-300 transition-colors">Cancel</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>