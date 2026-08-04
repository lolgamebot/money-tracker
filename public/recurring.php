<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

// Run generator to catch up any missing entries
require "process_recurring.php";
processRecurring($pdo, $userId);

// Handle stop recurring (CSRF-protected)
if (isset($_GET["cancel"])) {
    verifyCsrfGet();
    $cancelId = (int)$_GET["cancel"];
    $cancelRecurring = $pdo->prepare("UPDATE expenses SET is_recurring = 0 WHERE id = ? AND user_id = ?");
    $cancelRecurring->execute([$cancelId, $userId]);
    setFlash("Recurring schedule stopped. Generated dashboard entries remain intact.");
    header("Location: recurring.php");
    exit;
}

// Handle delete all records (CSRF-protected)
if (isset($_GET["delete_all"])) {
    verifyCsrfGet();
    $deleteId = (int)$_GET["delete_all"];
    $deleteAll = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND (id = ? OR parent_id = ?)");
    $deleteAll->execute([$userId, $deleteId, $deleteId]);
    setFlash("Deleted recurring template and all associated dashboard records.");
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

// Count generated children for each recurring record (single query)
$generatedCounts = [];
if (!empty($recurringRecords)) {
    $ids          = array_column($recurringRecords, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $countStmt = $pdo->prepare("
        SELECT parent_id, COUNT(*) AS cnt
        FROM expenses
        WHERE user_id = ? AND parent_id IN ($placeholders)
        GROUP BY parent_id
    ");
    $countStmt->execute(array_merge([$userId], $ids));
    foreach ($countStmt->fetchAll() as $row) {
        $generatedCounts[$row["parent_id"]] = $row["cnt"];
    }
    foreach ($ids as $id) {
        $generatedCounts[$id] = ($generatedCounts[$id] ?? 0) + 1; // +1 for the template itself
    }
}
?>

<?php renderHeader('Recurring Records'); ?>

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <?= svgIcon('refresh', 'h-7 w-7 text-indigo-400') ?>
                    Recurring Records
                </h1>
                <p class="text-slate-400 text-sm mt-1">Manage scheduled automated income and expenses.</p>
            </div>
            <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <?= svgIcon('plus') ?>
                Add Recurring
            </a>
        </div>

        <?php renderFlash(); ?>

        <?php if (count($recurringRecords) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-8 sm:p-10 text-center">
                <div class="inline-flex p-3 rounded-full bg-slate-800 text-slate-400 mb-3">
                    <?= svgIcon('refresh', 'h-6 w-6') ?>
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
                                        <?= $record['type'] == 'income' ? '+' : '-' ?>₱<?= formatMoney($record["amount"]) ?>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-300 capitalize">
                                        <span class="inline-flex items-center gap-1.5">
                                            <?= svgIcon('refresh') ?>
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
                                                <?= svgIcon('edit') ?>
                                                Edit
                                            </a>
                                            <a href="recurring.php?cancel=<?= $record["id"] ?><?= getCsrfQueryParam() ?>"
                                               onclick="return confirm('Stop recurring schedule?\n\nFuture entries will no longer automatically generate, but existing records on your dashboard will remain intact.')"
                                               class="text-xs bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/30 px-2.5 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                                <?= svgIcon('stop') ?>
                                                Stop
                                            </a>
                                            <a href="recurring.php?delete_all=<?= $record["id"] ?><?= getCsrfQueryParam() ?>"
                                               onclick="return confirm('Are you sure you want to delete this recurring expense AND ALL <?= $count ?> generated record(s) from your dashboard?')"
                                               class="text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2.5 py-1.5 rounded-lg font-medium transition-colors inline-flex items-center gap-1.5">
                                                <?= svgIcon('trash') ?>
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
                                <?= $record['type'] == 'income' ? '+' : '-' ?>₱<?= formatMoney($record["amount"]) ?>
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
                                <?= svgIcon('refresh') ?> <?= ucfirst($record["recurring_interval"]) ?>
                            </span>
                            <span class="bg-slate-800 text-slate-400 text-xs px-2 py-0.5 rounded border border-slate-700">
                                <?= $count ?> generated
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-slate-800/80">
                            <a href="edit.php?id=<?= $record["id"] ?>" class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-1.5 rounded-lg border border-slate-700 inline-flex items-center gap-1.5"><?= svgIcon('edit') ?> Edit</a>
                            <a href="recurring.php?cancel=<?= $record["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Stop recurring schedule? Future entries will stop generating.')" class="text-xs bg-amber-500/10 text-amber-400 border border-amber-500/30 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5"><?= svgIcon('stop') ?> Stop</a>
                            <a href="recurring.php?delete_all=<?= $record["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Delete this recurring expense AND ALL <?= $count ?> generated records?')" class="text-xs bg-rose-500/10 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-lg font-medium inline-flex items-center gap-1.5"><?= svgIcon('trash') ?> Delete All</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php renderFooter(); ?>

</content>
