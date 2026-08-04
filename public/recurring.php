<?php
session_start();
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

// Run generator to catch up any missing entries
require "process_recurring.php";
processRecurring($pdo, $userId);

// Handle stop recurring (CSRF-protected)
if (isset($_GET["cancel"])) {
    verifyCsrfGet();
    $cancelId = (int)$_GET["cancel"];
    $cancelRecurring = $pdo->prepare("UPDATE expenses SET is_recurring = 0 WHERE id = ? AND user_id = ?");
    $cancelRecurring->execute([$cancelId, $userId]);
    $_SESSION["flash_success"] = "Recurring schedule stopped. Generated dashboard entries remain intact.";
    header("Location: recurring.php");
    exit;
}

// Handle delete all records (CSRF-protected)
if (isset($_GET["delete_all"])) {
    verifyCsrfGet();
    $deleteId = (int)$_GET["delete_all"];
    $deleteAll = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND (id = ? OR parent_id = ?)");
    $deleteAll->execute([$userId, $deleteId, $deleteId]);
    $_SESSION["flash_success"] = "Deleted recurring template and all associated dashboard records.";
    header("Location: recurring.php");
    exit;
}

// Fetch all active recurring templates
$getRecurring = $pdo->prepare("
    SELECT expenses.*, categories.name AS category_name
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ? AND expenses.is_recurring = 1
    ORDER BY expenses.date DESC
");
$getRecurring->execute([$userId]);
$recurringRecords = $getRecurring->fetchAll();

// Count generated children for each recurring record
$generatedCounts = [];
foreach ($recurringRecords as $record) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM expenses WHERE user_id = ? AND (parent_id = ? OR id = ?)");
    $countStmt->execute([$userId, $record["id"], $record["id"]]);
    $generatedCounts[$record["id"]] = $countStmt->fetchColumn();
}

// SVG icon snippets
$refreshIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>';
$editIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
$stopIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
$trashIcon = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recurring Records - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Recurring Records
                </h1>
                <p class="text-slate-400 text-sm mt-1">Manage scheduled automated income and expenses.</p>
            </div>
            <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Recurring
            </a>
        </div>

        <?php if (isset($_SESSION["flash_success"])): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm flex items-center justify-between">
                <span><?= e($_SESSION["flash_success"]) ?></span>
                <?php unset($_SESSION["flash_success"]); ?>
            </div>
        <?php endif; ?>

        <?php if (count($recurringRecords) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-8 sm:p-10 text-center">
                <div class="inline-flex p-3 rounded-full bg-slate-800 text-slate-400 mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white mb-1">No Active Recurring Records</h3>
                <p class="text-slate-400 text-sm mb-4">Set up automated recurring expenses or income so they repeat on your schedule.</p>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors inline-block">Create Recurring Record</a>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden md:block bg-[#111827] rounded-xl border border-slate-700 overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-slate-400 uppercase bg-[#0d1322] border-b border-slate-700">
                            <tr>
                                <th class="py-3.5 px-4 font-semibold">Description</th>
                                <th class="py-3.5 px-4 font-semibold">Type</th>
                                <th class="py-3.5 px-4 font-semibold">Category</th>
                                <th class="py-3.5 px-4 font-semibold">Amount</th>
                                <th class="py-3.5 px-4 font-semibold">Frequency</th>
                                <th class="py-3.5 px-4 font-semibold">Start Date</th>
                                <th class="py-3.5 px-4 font-semibold">End Date</th>
                                <th class="py-3.5 px-4 font-semibold text-center">Dashboard Entries</th>
                                <th class="py-3.5 px-4 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($recurringRecords as $record): ?>
                                <?php $count = $generatedCounts[$record["id"]] ?? 1; ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="py-3.5 px-4 text-white font-medium">
                                        <?= e($record["description"] ?: "—") ?>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="text-xs font-medium px-2.5 py-1 rounded-full <?= $record['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                            <?= ucfirst($record['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs font-medium px-2.5 py-1 rounded-full border border-indigo-500/20">
                                            <?= e($record["category_name"]) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 font-semibold whitespace-nowrap <?= $record['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= $record['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($record["amount"], 2) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-300 capitalize">
                                        <span class="inline-flex items-center gap-1.5">
                                            <?= $refreshIcon ?>
                                            <?= ucfirst($record["recurring_interval"]) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-400 whitespace-nowrap"><?= e($record["date"]) ?></td>
                                    <td class="py-3.5 px-4 text-slate-400 whitespace-nowrap">
                                        <?= $record["recurring_end_date"] ? e($record["recurring_end_date"]) : '<span class="text-indigo-400 font-medium">Infinite</span>' ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                        <span class="bg-slate-800 text-slate-300 text-xs px-2.5 py-1 rounded-md font-semibold border border-slate-700 whitespace-nowrap">
                                            <?= $count ?> record<?= $count > 1 ? 's' : '' ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="edit.php?id=<?= $record["id"] ?>" 
                                               class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-2.5 py-1.5 rounded-lg border border-slate-700 transition-colors inline-flex items-center gap-1.5">
                                                <?= $editIcon ?>
                                                Edit
                                            </a>
                                            <a href="recurring.php?cancel=<?= $record["id"] ?><?= getCsrfQueryParam() ?>"
                                               onclick="return confirm('Stop recurring schedule?\n\nFuture entries will no longer automatically generate, but existing records on your dashboard will remain intact.')"
                                               class="text-xs bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2.5 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                                <?= $stopIcon ?>
                                                Stop
                                            </a>
                                            <a href="recurring.php?delete_all=<?= $record["id"] ?><?= getCsrfQueryParam() ?>"
                                               onclick="return confirm('Are you sure you want to delete this recurring expense AND ALL <?= $count ?> generated record(s) from your dashboard?')"
                                               class="text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2.5 py-1.5 rounded-lg font-medium transition-colors inline-flex items-center gap-1.5">
                                                <?= $trashIcon ?>
                                                Delete All
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Card View -->
            <div class="block md:hidden space-y-4">
                <?php foreach ($recurringRecords as $record): ?>
                    <?php $count = $generatedCounts[$record["id"]] ?? 1; ?>
                    <div class="bg-[#111827] border border-slate-700 rounded-xl p-4 space-y-3 shadow-md">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="font-bold text-white text-base">
                                    <?= e($record["description"] ?: ($record["category_name"] . " " . ucfirst($record["type"]))) ?>
                                </h3>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Started: <?= e($record["date"]) ?> • Ends: <?= $record["recurring_end_date"] ? e($record["recurring_end_date"]) : 'Infinite' ?>
                                </p>
                            </div>
                            <span class="font-bold text-lg whitespace-nowrap <?= $record['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                <?= $record['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($record["amount"], 2) ?>
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-800">
                            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full <?= $record['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                <?= ucfirst($record['type']) ?>
                            </span>
                            <span class="bg-indigo-500/10 text-indigo-400 text-xs font-medium px-2.5 py-0.5 rounded-full border border-indigo-500/20">
                                <?= e($record["category_name"]) ?>
                            </span>
                            <span class="bg-slate-800 text-slate-300 text-xs px-2.5 py-0.5 rounded-full border border-slate-700 capitalize inline-flex items-center gap-1">
                                <?= $refreshIcon ?> <?= ucfirst($record["recurring_interval"]) ?>
                            </span>
                            <span class="bg-slate-800 text-slate-400 text-xs px-2 py-0.5 rounded border border-slate-700">
                                <?= $count ?> generated
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
                            <a href="edit.php?id=<?= $record["id"] ?>" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-700 inline-flex items-center gap-1.5"><?= $editIcon ?> Edit</a>
                            <a href="recurring.php?cancel=<?= $record["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Stop recurring schedule? Future entries will stop generating.')" class="text-xs bg-amber-500/10 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5"><?= $stopIcon ?> Stop</a>
                            <a href="recurring.php?delete_all=<?= $record["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Delete this recurring expense AND ALL <?= $count ?> generated records?')" class="text-xs bg-rose-500/10 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-lg font-medium inline-flex items-center gap-1.5"><?= $trashIcon ?> Delete All</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>