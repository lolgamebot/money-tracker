<?php
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
    $cancelRecurring = $pdo->prepare(
        "UPDATE expenses
         SET is_recurring = 0, recurring_end_date = ?
         WHERE id = ? AND user_id = ?
           AND is_recurring = 1
           AND parent_id IS NULL
           AND recurring_end_date IS NULL"
    );
$cancelRecurring->execute([date('Y-m-d'), $cancelId, $userId]);

    if ($cancelRecurring->rowCount() === 0) {
        setFlash("That recurring record is no longer available.");
        if (isAjaxRequest()) respondJson(['success' => false, 'message' => 'That recurring record is no longer available.']);
        header("Location: recurring.php");
        exit;
    }

    if (isAjaxRequest()) respondJson(['success' => true, 'message' => 'Recurring schedule stopped.']);
    setFlash("Recurring schedule stopped. Generated dashboard entries remain intact.");
    header("Location: recurring.php");
    exit;
}

// Handle "remove" from the Bills Due This Month widget (CSRF-protected)
if (isset($_GET["remove_bill"])) {
    verifyCsrfGet();
    $billId = (int)$_GET["remove_bill"];
    $bill   = getUpcomingBillRemovalTarget($pdo, $userId, $billId);

if (!$bill) {
        setFlash("That bill is not currently listed in Bills Due This Month.");
        if (isAjaxRequest()) respondJson(['success' => false, 'message' => 'That bill is not currently listed in Bills Due This Month.']);
        header("Location: recurring.php");
        exit;
    }

        if ($bill["source"] === "recurring") {
        $stopRecurring = $pdo->prepare("UPDATE expenses SET is_recurring = 0, recurring_end_date = ? WHERE id = ? AND user_id = ?");
        $stopRecurring->execute([date('Y-m-d'), $billId, $userId]);
    } else {
        $deleteBill = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ? AND is_recurring = 0 AND parent_id IS NULL AND recurring_end_date IS NULL");
        $deleteBill->execute([$billId, $userId]);
    }

    if (isAjaxRequest()) respondJson(['success' => true, 'message' => 'Bill removed.']);
    setFlash($bill["source"] === "recurring" ? "Recurring bill removed." : "Bill removed.");
    header("Location: recurring.php");
    exit;
}

// Handle "delete series" (CSRF-protected): removes the recurring template
// and future pending occurrences, but keeps already-generated past records.
if (isset($_GET["delete_all"])) {
    verifyCsrfGet();
    $deleteId = (int)$_GET["delete_all"];

    // Delete the recurring template itself (stops future generation)
    $deleteTemplate = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $deleteTemplate->execute([$deleteId, $userId]);

    // Also delete any generated child records whose date is in the future
    // (upcoming), leaving past records intact.
    $deleteFuture = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND parent_id = ? AND date >= ?");
$deleteFuture->execute([$userId, $deleteId, date('Y-m-d')]);

if (isAjaxRequest()) respondJson(['success' => true, 'message' => 'Deleted recurring template and upcoming records.']);
    setFlash("Deleted recurring template and upcoming records. Past records remain intact.");
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

// Handle "mark as paid" toggle (CSRF-protected)
if (isset($_GET["mark_paid"])) {
    verifyCsrfGet();
    $billId = (int)$_GET["mark_paid"];
$paidToggle = isset($_GET["unpaid"]) ? 0 : 1;
    markBillPaid($pdo, $userId, $billId, (bool)$paidToggle);
    if (isAjaxRequest()) respondJson(['success' => true, 'message' => $paidToggle ? 'Bill marked as paid!' : 'Bill marked as unpaid.']);
    setFlash($paidToggle ? "Bill marked as paid!" : "Bill marked as unpaid.");
    header("Location: recurring.php");
    exit;
}

// Upcoming bills for the current month
$upcomingBills = getUpcomingBills($pdo, $userId);

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

<?php $modal = isset($_GET["modal"]) || isAjaxRequest(); ?>

<?php if ($modal): ?>
<title data-modal-title>Recurring Records</title>
<?php endif; ?>

<?php if (!$modal): ?>
<?php renderHeader('Recurring Records'); ?>

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
<?php else: ?>
    <div>
<?php endif; ?>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                    <?= svgIcon('refresh', 'h-7 w-7 text-indigo-400') ?>
                    Recurring Records
                </h1>
                <p class="text-slate-400 text-sm mt-1">Manage scheduled automated income and expenses.</p>
            </div>
<a href="add.php" data-modal-uri="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2">
                <?= svgIcon('plus') ?>
                Add Recurring
            </a>
        </div>

<?php renderFlash(); ?>

        <!-- Upcoming Bills / Payments This Month -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <?= svgIcon('calendar', 'h-5 w-5 text-indigo-400') ?>
                    Bills Due This Month
                </h2>
                <span class="text-slate-400 text-sm"><?= date("F Y") ?></span>
            </div>

            <?php if (count($upcomingBills) === 0): ?>
                <p class="text-slate-400 text-sm">No bills due this month. 🎉</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($upcomingBills as $bill): ?>
                        <?php $isPaid = (bool)$bill["paid"]; ?>
                        <div class="flex items-center justify-between gap-3 py-2.5 border-b border-slate-800 last:border-0 <?= $isPaid ? 'opacity-60' : '' ?>">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="flex-1 min-w-0">
                                    <p class="text-slate-200 text-sm font-medium truncate">
                                        <?= e($bill["description"] ?: ($bill["category_name"] . " " . ucfirst($bill["type"]))) ?>
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        <?= e($bill["category_name"] ?: 'Uncategorized') ?> •
                                        Due <?= e(formatDueDate($bill["date"])) ?>
                                        <?php if ($bill["source"] === 'recurring'): ?>
                                            • <span class="text-indigo-400 inline-flex items-center gap-1"><?= svgIcon('refresh', 'h-3 w-3') ?> Recurring</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="font-bold text-sm text-rose-400 whitespace-nowrap">₱<?= formatMoney($bill["amount"]) ?></span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <?php if ($isPaid): ?>
                                    <span class="inline-flex items-center gap-1 text-emerald-400 text-xs bg-emerald-500/10 border border-emerald-500/30 px-2 py-1 rounded-md font-medium">
                                        <?= svgIcon('check', 'h-3.5 w-3.5') ?> Paid
                                    </span>
                                    <a href="recurring.php?mark_paid=<?= (int)$bill["id"] ?>&unpaid=1<?= getCsrfQueryParam() ?>"
                                       class="text-slate-400 hover:text-white text-xs transition-colors" title="Mark as unpaid">Undo</a>
                                <?php else: ?>
                                    <a href="recurring.php?mark_paid=<?= (int)$bill["id"] ?><?= getCsrfQueryParam() ?>"
                                       class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                        <?= svgIcon('check', 'h-3.5 w-3.5') ?>
                                        Confirm Paid
                                    </a>
                                <?php endif; ?>
                                <a href="recurring.php?remove_bill=<?= (int)$bill["id"] ?>&source=<?= e($bill["source"]) ?><?= getCsrfQueryParam() ?>"
                                   onclick="return confirm('<?= $bill["source"] === "recurring" ? "Stop this recurring bill? It will no longer show up in Bills Due This Month." : "Remove this bill? This deletes it." ?>')"
                                   class="text-slate-500 hover:text-rose-400 transition-colors" title="Remove from Bills Due This Month">
                                    <?= svgIcon('trash', 'h-3.5 w-3.5') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if (count($recurringRecords) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-8 sm:p-10 text-center">
                <div class="inline-flex p-3 rounded-full bg-slate-800 text-slate-400 mb-3">
                    <?= svgIcon('refresh', 'h-6 w-6') ?>
                </div>
                <h3 class="text-lg font-semibold text-white mb-1">No Active Recurring Records</h3>
                <p class="text-slate-400 text-sm mb-4">Set up automated recurring expenses or income so they repeat on your schedule.</p>
<a href="add.php" data-modal-uri="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition-colors inline-block">Create Recurring Record</a>
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
                                               onclick="return confirm('Delete this recurring schedule? It will stop future automatic entries. Past records will remain on your dashboard.')"
                                               class="text-xs bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2.5 py-1.5 rounded-lg font-medium transition-colors inline-flex items-center gap-1.5">
                                                <?= svgIcon('trash') ?>
                                                Delete Series
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
<a href="recurring.php?delete_all=<?= $record["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Delete this recurring schedule? It will stop future automatic entries. Past records will remain.')" class="text-xs bg-rose-500/10 text-rose-400 border border-rose-500/30 px-3 py-1.5 rounded-lg font-medium inline-flex items-center gap-1.5"><?= svgIcon('trash') ?> Delete Series</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
<?php endif; ?>
    </div>

<?php if (!$modal): ?>
<?php renderFooter(); ?>
<?php endif; ?>
