<?php
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

require "process_recurring.php";
processRecurring($pdo, $userId);

// Filter values
$search          = $_GET["search"] ?? "";
$filterType      = $_GET["type"] ?? "";
$filterCategory  = $_GET["category"] ?? "";
$filterDateFrom  = $_GET["date_from"] ?? "";
$filterDateTo    = $_GET["date_to"] ?? "";
$filterStatus    = $_GET["status"] ?? "";

// Pagination
$perPage     = 10;
$currentPage = isset($_GET["page"]) ? max(1, (int)$_GET["page"]) : 1;
$offset      = ($currentPage - 1) * $perPage;

// Build base filter conditions
$conditions = "WHERE expenses.user_id = ?";
$params     = [$userId];

if (!empty($search)) {
    $conditions .= " AND expenses.description LIKE ?";
    $escaped = str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], $search);
    $params[] = "%" . $escaped . "%";
}
if (!empty($filterType)) {
    $conditions .= " AND expenses.type = ?";
    $params[] = $filterType;
}
if (!empty($filterCategory)) {
    $conditions .= " AND expenses.category_id = ?";
    $params[] = $filterCategory;
}
if (!empty($filterDateFrom)) {
    $conditions .= " AND expenses.date >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $conditions .= " AND expenses.date <= ?";
    $params[] = $filterDateTo;
}
if ($filterStatus === "paid") {
    $conditions .= " AND expenses.paid = 1";
} elseif ($filterStatus === "unpaid") {
    $conditions .= " AND expenses.paid = 0";
}

// Count total records for pagination
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM expenses LEFT JOIN categories ON expenses.category_id = categories.id $conditions");
$countQuery->execute($params);
$totalRecords = $countQuery->fetchColumn();
$totalPages   = ceil($totalRecords / $perPage);

// Fetch paginated records
$getExpenses = $pdo->prepare("
    SELECT expenses.*, categories.name AS category_name
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    $conditions
    ORDER BY expenses.date DESC
    LIMIT $perPage OFFSET $offset
");
$getExpenses->execute($params);
$expenses = $getExpenses->fetchAll();

// CSV export of the current filtered view (ignores pagination)
if (isset($_GET["export"])) {
    $exportQuery = $pdo->prepare("
        SELECT expenses.id, expenses.date, expenses.type, categories.name AS category_name, expenses.description, expenses.amount
        FROM expenses
        LEFT JOIN categories ON expenses.category_id = categories.id
        $conditions
        ORDER BY expenses.date DESC
    ");
    $exportQuery->execute($params);
    $exportRows = $exportQuery->fetchAll();

    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"moneytracker-export-" . date("Y-m-d") . ".csv\"");
    $out = fopen("php://output", "w");
    // UTF-8 BOM so Excel renders the peso sign correctly
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ["ID", "Date", "Type", "Category", "Description", "Amount"]);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            $row["id"],
            $row["date"],
            $row["type"],
            $row["category_name"] ?? "",
            $row["description"],
            $row["amount"],
        ]);
    }
    fclose($out);
    exit;
}

// Totals of the current filtered view (for the results header)
$filteredTotals = ['income' => 0.0, 'expense' => 0.0];
if ($totalRecords > 0) {
    $filteredStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN expenses.type = 'income' THEN expenses.amount END), 0) AS income,
            COALESCE(SUM(CASE WHEN expenses.type = 'expense' THEN expenses.amount END), 0) AS expense
        FROM expenses
        LEFT JOIN categories ON expenses.category_id = categories.id
        $conditions
    ");
    $filteredStmt->execute($params);
    $filteredRow = $filteredStmt->fetch();
    $filteredTotals['income']  = (float)($filteredRow['income'] ?? 0);
    $filteredTotals['expense'] = (float)($filteredRow['expense'] ?? 0);
}

// Summary totals
$getTotals = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS total_expense,
        COALESCE(SUM(CASE WHEN type = 'expense' AND MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE()) THEN amount END), 0) AS month_total
    FROM expenses
    WHERE user_id = ?
");
$getTotals->execute([$userId]);
$totals       = $getTotals->fetch();
$totalIncome  = $totals["total_income"] ?? 0;
$totalExpense = $totals["total_expense"] ?? 0;
$monthTotal   = $totals["month_total"] ?? 0;
$balance      = $totalIncome - $totalExpense;

// Previous month totals (for month-over-month deltas on the summary cards)
$getPrevMonth = $pdo->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) AS prev_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) AS prev_expense
    FROM expenses
    WHERE user_id = ?
      AND YEAR(date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
      AND MONTH(date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
");
$getPrevMonth->execute([$userId]);
$prevTotals = $getPrevMonth->fetch();
$prevIncome  = (float)($prevTotals["prev_income"] ?? 0);
$prevExpense = (float)($prevTotals["prev_expense"] ?? 0);

/** Percent change vs previous month; null when there is no baseline. */
function pctChange($current, $previous) {
    if ($previous <= 0) return null;
    return round((($current - $previous) / $previous) * 100, 1);
}

$incomeDelta  = pctChange((float)$totalIncome, $prevIncome);
$expenseDelta = pctChange((float)$totalExpense, $prevExpense);

$getCategoryTotals = $pdo->prepare("
    SELECT categories.name, SUM(expenses.amount) AS total
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ? AND expenses.type = 'expense'
    GROUP BY categories.name
    ORDER BY total DESC
");
$getCategoryTotals->execute([$userId]);
$categoryTotals = $getCategoryTotals->fetchAll();

$categories = getCategories($pdo, $userId);

// Handle "mark as paid" toggle (CSRF-protected)
if (isset($_GET["mark_paid"])) {
    verifyCsrfGet();
    $billId = (int)$_GET["mark_paid"];
    $paidToggle = isset($_GET["unpaid"]) ? 0 : 1;
markBillPaid($pdo, $userId, $billId, (bool)$paidToggle);
    setFlash($paidToggle ? "Record marked as paid!" : "Record marked as unpaid.");
// AJAX requests update the UI in place (no full page reload).
    if (isAjaxRequest()) {
        // Fetch the record type so the client can render the correct status label.
        $typeStmt = $pdo->prepare("SELECT type FROM expenses WHERE id = ? AND user_id = ?");
        $typeStmt->execute([$billId, $userId]);
        $type = $typeStmt->fetchColumn() ?: 'expense';
        respondJson(['success' => true, 'id' => $billId, 'paid' => (bool)$paidToggle, 'type' => $type]);
    }
    header("Location: index.php?" . http_build_query(array_filter([
        "search"    => $search,
        "type"      => $filterType,
        "category"  => $filterCategory,
        "date_from" => $filterDateFrom,
        "date_to"   => $filterDateTo,
        "status"    => $filterStatus,
        "page"      => $currentPage,
    ])));
    exit;
}

// Handle "remove" from the Upcoming Bills widget (CSRF-protected).
// One-off bills are just a single future-dated row, so remove = delete it.
// Recurring bills are stopped (same as the "Stop" action on the Recurring
// page) so they quit generating new upcoming occurrences — already
// generated past records are left untouched.
if (isset($_GET["remove_bill"])) {
    verifyCsrfGet();
    $billId = (int)$_GET["remove_bill"];
    $bill   = getUpcomingBillRemovalTarget($pdo, $userId, $billId);

    if (!$bill) {
        setFlash("That bill is not currently listed in Upcoming Bills.");
        header("Location: index.php");
        exit;
    }

    if ($bill["source"] === "recurring") {
        $stopRecurring = $pdo->prepare("UPDATE expenses SET is_recurring = 0, recurring_end_date = ? WHERE id = ? AND user_id = ?");
        $stopRecurring->execute([date('Y-m-d'), $billId, $userId]);
        setFlash("Recurring bill removed from Upcoming Bills.");
    } else {
        $deleteBill = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ? AND is_recurring = 0 AND parent_id IS NULL AND recurring_end_date IS NULL");
        $deleteBill->execute([$billId, $userId]);
        setFlash("Bill removed.");
    }

    header("Location: index.php");
    exit;
}

// Upcoming bills for the current month
$upcomingBills = getUpcomingBills($pdo, $userId);
$unpaidBills = array_filter($upcomingBills, function ($b) { return !$b['paid']; });
$paidBills   = array_filter($upcomingBills, function ($b) { return $b['paid']; });

// Build query string for pagination links (no page param — those append their own)
$queryString = http_build_query(array_filter([
    "search"      => $search,
    "type"        => $filterType,
    "category"    => $filterCategory,
    "date_from"   => $filterDateFrom,
    "date_to"     => $filterDateTo,
    "status"      => $filterStatus,
]));

// Build query string for status toggle links that preserve the current page
$markQueryString = http_build_query(array_filter([
    "search"      => $search,
    "type"        => $filterType,
    "category"    => $filterCategory,
    "date_from"   => $filterDateFrom,
    "date_to"     => $filterDateTo,
    "status"      => $filterStatus,
    "page"        => $currentPage,
]));

$hasActiveFilter = !empty($search) || !empty($filterType) || !empty($filterCategory) || !empty($filterDateFrom) || !empty($filterDateTo) || !empty($filterStatus);
?>

<?php renderHeader('Dashboard', ['flatpickr' => true, 'chartjs' => true]); ?>

    <?php renderNav(); ?>

    <?php renderModalSystem(); ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Welcome back, <?= e($_SESSION["username"]) ?>!</h1>

        <?php renderFlash(); ?>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-8">
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <div class="flex items-center gap-2 mb-1">
                    <?= svgIcon('income', 'h-4 w-4 text-emerald-400') ?>
                    <p class="text-slate-400 text-xs sm:text-sm">Total Income</p>
                </div>
                <p class="text-xl sm:text-2xl font-bold text-emerald-400">₱<?= formatMoney($totalIncome) ?></p>
                <?php if ($incomeDelta !== null): ?>
                    <p class="text-[11px] mt-1 <?= $incomeDelta >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">
                        <?= $incomeDelta >= 0 ? svgIcon('income', 'h-3 w-3') . '+' : '' ?><?= $incomeDelta ?>% vs last month
                    </p>
                <?php endif; ?>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <div class="flex items-center gap-2 mb-1">
                    <?= svgIcon('expense', 'h-4 w-4 text-rose-400') ?>
                    <p class="text-slate-400 text-xs sm:text-sm">Total Expenses</p>
                </div>
                <p class="text-xl sm:text-2xl font-bold text-rose-400">₱<?= formatMoney($totalExpense) ?></p>
                <?php if ($expenseDelta !== null): ?>
                    <p class="text-[11px] mt-1 <?= $expenseDelta > 0 ? 'text-rose-400' : 'text-emerald-400' ?>">
                        <?= $expenseDelta > 0 ? svgIcon('expense', 'h-3 w-3') . '+' : '' ?><?= $expenseDelta ?>% vs last month
                    </p>
                <?php endif; ?>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <div class="flex items-center gap-2 mb-1">
                    <?= svgIcon('balance', 'h-4 w-4 text-white') ?>
                    <p class="text-slate-400 text-xs sm:text-sm">Balance</p>
                </div>
                <p class="text-xl sm:text-2xl font-bold <?= $balance >= 0 ? 'text-white' : 'text-rose-400' ?>">
                    ₱<?= formatMoney($balance) ?>
                </p>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <div class="flex items-center gap-2 mb-1">
                    <?= svgIcon('month', 'h-4 w-4 text-indigo-400') ?>
                    <p class="text-slate-400 text-xs sm:text-sm">This Month</p>
                </div>
                <p class="text-xl sm:text-2xl font-bold text-indigo-400">₱<?= formatMoney($monthTotal) ?></p>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <?= svgIcon('category', 'h-5 w-5 text-indigo-400') ?>
                Spending by Category
            </h2>
            <?php if (count($categoryTotals) === 0): ?>
                <p class="text-slate-400 text-sm">No expense data yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($categoryTotals as $cat): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                            <span class="text-slate-300 text-sm"><?= e($cat["name"]) ?></span>
                            <span class="text-rose-400 font-semibold text-sm">₱<?= formatMoney($cat["total"]) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Upcoming Bills / Payments -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <?= svgIcon('calendar', 'h-5 w-5 text-indigo-400') ?>
                    Upcoming Bills
                </h2>
<a href="add.php" data-modal-uri="add.php" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition-colors flex items-center gap-1.5">
                    <?= svgIcon('plus', 'h-4 w-4') ?>
                    Add Bill
                </a>
            </div>

            <?php if (count($upcomingBills) === 0): ?>
                <p class="text-slate-400 text-sm">No upcoming bills this month. 🎉</p>
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
                                    <a href="index.php?mark_paid=<?= (int)$bill["id"] ?>&unpaid=1<?= getCsrfQueryParam() ?>"
                                       class="text-slate-400 hover:text-white text-xs transition-colors" title="Mark as unpaid">Undo</a>
                                <?php else: ?>
                                    <a href="index.php?mark_paid=<?= (int)$bill["id"] ?><?= getCsrfQueryParam() ?>"
                                       class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                        <?= svgIcon('check', 'h-3.5 w-3.5') ?>
                                        Confirm Paid
                                    </a>
                                <?php endif; ?>
                                <a href="index.php?remove_bill=<?= (int)$bill["id"] ?>&source=<?= e($bill["source"]) ?><?= getCsrfQueryParam() ?>"
                                   onclick="return confirm('<?= $bill["source"] === "recurring" ? "Stop this recurring bill? It will no longer show up in Upcoming Bills." : "Remove this bill? This deletes it." ?>')"
                                   class="text-slate-500 hover:text-rose-400 transition-colors" title="Remove from Upcoming Bills">
                                    <?= svgIcon('trash', 'h-3.5 w-3.5') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Records List -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white flex items-center gap-2">
                    <?= svgIcon('clipboard', 'h-5 w-5 text-indigo-400') ?>
                    All Records
                </h2>
<a href="add.php" data-modal-uri="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs sm:text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors flex items-center gap-1.5">
                    <?= svgIcon('plus') ?>
                    Add Record
                </a>
            </div>

            <!-- Search and Filter -->
            <form action="index.php" method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                <input type="text" name="search" placeholder="Search description..."
                    value="<?= e($search) ?>"
                    class="<?= INPUT_CLASS ?>">

                <select name="type" class="<?= INPUT_CLASS ?>">
                    <option value="">All Types</option>
                    <option value="income" <?= $filterType == "income" ? "selected" : "" ?>>Income</option>
                    <option value="expense" <?= $filterType == "expense" ? "selected" : "" ?>>Expense</option>
                </select>

<select name="category" class="<?= INPUT_CLASS ?>">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int)$category["id"] ?>" <?= $filterCategory == $category["id"] ? "selected" : "" ?>>
                            <?= e($category["name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="<?= INPUT_CLASS ?>">
                    <option value="">All Status</option>
                    <option value="paid" <?= $filterStatus === "paid" ? "selected" : "" ?>>Paid / Received</option>
                    <option value="unpaid" <?= $filterStatus === "unpaid" ? "selected" : "" ?>>Unpaid / Pending</option>
                </select>

                <div class="flex gap-2 items-center">
                    <label class="text-slate-400 text-sm whitespace-nowrap">From:</label>
                    <div class="relative w-full cursor-pointer">
                        <input type="text" id="dateFrom" name="date_from" value="<?= e($filterDateFrom) ?>" placeholder="YYYY-MM-DD"
                            class="<?= INPUT_CLASS ?> cursor-pointer pl-3 pr-8">
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-indigo-400">
                            <?= svgIcon('calendar', 'h-4 w-4') ?>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 items-center">
                    <label class="text-slate-400 text-sm whitespace-nowrap">To:</label>
                    <div class="relative w-full cursor-pointer">
                        <input type="text" id="dateTo" name="date_to" value="<?= e($filterDateTo) ?>" placeholder="YYYY-MM-DD"
                            class="<?= INPUT_CLASS ?> cursor-pointer pl-3 pr-8">
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-indigo-400">
                            <?= svgIcon('calendar', 'h-4 w-4') ?>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-1.5">
                        <?= svgIcon('search') ?>
                        Filter
                    </button>
                    <a href="index.php?export=1&<?= $queryString ?>"
                        class="flex-1 text-center bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium py-2.5 rounded-lg transition-colors" title="Download current results as CSV">
                        <?= svgIcon('download', 'h-4 w-4') ?> Export CSV
                    </a>
                    <a href="index.php"
                        class="flex-1 text-center bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium py-2.5 rounded-lg transition-colors">
                        Clear
                    </a>
                </div>
            </form>

            <!-- Results count -->
            <p class="text-slate-400 text-sm mb-4">
                Showing <span class="text-white font-medium"><?= count($expenses) ?></span> of
                <span class="text-white font-medium"><?= $totalRecords ?></span> record(s)
                — Page <span class="text-white font-medium"><?= $currentPage ?></span> of
                <span class="text-white font-medium"><?= max($totalPages, 1) ?></span>
                <?= $hasActiveFilter ? '— <a href="index.php" class="text-indigo-400 hover:underline">Clear filters</a>' : '' ?>
            </p>
            <?php if ($filteredTotals['income'] > 0 || $filteredTotals['expense'] > 0): ?>
                <p class="text-xs text-slate-400 mb-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                    Filtered totals:
                    <span class="text-emerald-400 font-medium">+₱<?= formatMoney($filteredTotals['income']) ?></span>
                    <span class="text-rose-400 font-medium">-₱<?= formatMoney($filteredTotals['expense']) ?></span>
                    <span class="text-slate-300 font-medium">Net ₱<?= formatMoney($filteredTotals['income'] - $filteredTotals['expense']) ?></span>
                </p>
            <?php endif; ?>

            <?php if (count($expenses) === 0): ?>
                <div class="text-center py-8">
                    <?= svgIcon('alert', 'h-10 w-10 text-slate-600 mx-auto mb-3') ?>
                    <p class="text-slate-400 text-sm">No records found. Try adjusting your filters or add a new record.</p>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-700">
                                <th class="text-left py-2.5 pr-4 whitespace-nowrap">Date</th>
                                <th class="text-left py-2.5 pr-4 whitespace-nowrap">Type</th>
                                <th class="text-left py-2.5 pr-4 whitespace-nowrap">Category</th>
                                <th class="text-left py-2.5 pr-4 whitespace-nowrap">Description</th>
<th class="text-right py-2.5 pr-4 whitespace-nowrap">Amount</th>
                                <th class="text-left py-2.5 pr-4 whitespace-nowrap">Status</th>
                                <th class="text-right py-2.5 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($expenses as $expense): ?>
<?php $isRecurringSeries = $expense['is_recurring'] || !empty($expense['parent_id']); ?>
                                <tr class="hover:bg-slate-800/30 transition-colors" data-status-row data-type="<?= e($expense['type']) ?>">
                                    <td class="py-3 pr-4 text-slate-400 whitespace-nowrap"><?= e($expense["date"]) ?></td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="text-xs px-2.5 py-1 rounded-full <?= $expense['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                            <?= ucfirst($expense['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2.5 py-1 rounded-full border border-indigo-500/20">
                                            <?= e($expense["category_name"] ?: 'Uncategorized') ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-300 max-w-[180px] truncate">
                                        <?= e($expense["description"] ?: "—") ?>
                                        <?php if ($isRecurringSeries): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded ml-1 font-medium" title="Recurring record">
                                                <?= svgIcon('refresh', 'h-3 w-3') ?> Recurring
                                            </span>
                                        <?php endif; ?>
                                    </td>
<td class="py-3 pr-4 text-right font-semibold whitespace-nowrap <?= $expense['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= $expense['type'] == 'income' ? '+' : '-' ?>₱<?= formatMoney($expense["amount"]) ?>
                                    </td>
                                    <?php $isPaid = (bool)$expense['paid']; ?>
                                    <td class="py-3 pr-4 whitespace-nowrap">
<span data-status-badge class="text-xs px-2.5 py-1 rounded-full font-medium <?= $isPaid ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' ?>">
                                            <?= $expense['type'] == 'income' ? ($isPaid ? 'Received' : 'Pending') : ($isPaid ? 'Paid' : 'Unpaid') ?>
                                        </span>
<a href="index.php?mark_paid=<?= (int)$expense['id'] ?><?= $isPaid ? '&unpaid=1' : '' ?><?= !empty($markQueryString) ? '&' . $markQueryString : '' ?><?= getCsrfQueryParam() ?>"
                                           class="text-indigo-400 hover:text-indigo-300 text-xs ml-2 transition-colors" title="Toggle status" data-mark-toggle>
                                            <?= $isPaid ? 'Undo' : 'Mark' ?>
                                        </a>
                                    </td>
                                    <td class="py-3 text-right whitespace-nowrap">
<a href="edit.php?id=<?= $expense["id"] ?>" data-modal-uri="edit.php?id=<?= $expense["id"] ?>" class="text-slate-400 hover:text-white mr-2 transition-colors text-xs inline-flex items-center gap-1"><?= svgIcon('edit', 'h-3.5 w-3.5') ?> Edit</a>
                                        <a href="delete.php?id=<?= $expense["id"] ?><?= getCsrfQueryParam() ?>"
                                           onclick="return confirm('Delete this record?')"
                                           class="text-rose-400 hover:text-rose-300 transition-colors text-xs inline-flex items-center gap-1"><?= svgIcon('trash', 'h-3.5 w-3.5') ?> Delete</a>
                                        <?php if ($isRecurringSeries): ?>
                                            <a href="delete.php?id=<?= $expense["id"] ?>&mode=all<?= getCsrfQueryParam() ?>"
                                               onclick="return confirm('Delete this record AND all other entries in this recurring series from your dashboard?')"
                                               class="text-rose-400 hover:text-rose-300 transition-colors text-xs bg-rose-500/10 border border-rose-500/30 px-2 py-1 rounded ml-2 inline-flex items-center gap-1"><?= svgIcon('trash', 'h-3.5 w-3.5') ?> Series</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View -->
                <div class="block md:hidden space-y-3">
                    <?php foreach ($expenses as $expense): ?>
<?php $isRecurringSeries = $expense['is_recurring'] || !empty($expense['parent_id']); ?>
                        <div class="bg-[#0a0f1e] border border-slate-700/80 rounded-xl p-4 space-y-2.5" data-status-row data-type="<?= e($expense['type']) ?>">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-white text-base">
                                        <?= e($expense["description"] ?: ($expense["category_name"] . " " . ucfirst($expense["type"]))) ?>
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= e($expense["date"]) ?></p>
                                </div>
                                <span class="font-bold text-base <?= $expense['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= $expense['type'] == 'income' ? '+' : '-' ?>₱<?= formatMoney($expense["amount"]) ?>
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-800">
                                <span class="text-xs px-2.5 py-0.5 rounded-full <?= $expense['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                    <?= ucfirst($expense['type']) ?>
                                </span>
<span class="bg-indigo-500/10 text-indigo-400 text-xs px-2.5 py-0.5 rounded-full border border-indigo-500/20">
                                    <?= e($expense["category_name"] ?: 'Uncategorized') ?>
                                </span>
<?php $isPaidMobile = (bool)$expense['paid']; ?>
                                <span data-status-badge class="text-xs px-2.5 py-0.5 rounded-full font-medium <?= $isPaidMobile ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' ?>">
                                    <?= $expense['type'] == 'income' ? ($isPaidMobile ? 'Received' : 'Pending') : ($isPaidMobile ? 'Paid' : 'Unpaid') ?>
                                </span>
                                <?php if ($isRecurringSeries): ?>
                                    <span class="text-[10px] bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded font-medium inline-flex items-center gap-1">
                                        <?= svgIcon('refresh', 'h-3 w-3') ?> Recurring
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2 text-xs border-t border-slate-800/80">
<a href="index.php?mark_paid=<?= (int)$expense['id'] ?><?= $isPaidMobile ? '&unpaid=1' : '' ?><?= !empty($markQueryString) ? '&' . $markQueryString : '' ?><?= getCsrfQueryParam() ?>"
                                   data-mark-toggle
                                   class="<?= $isPaidMobile ? 'text-slate-400 bg-slate-800 border border-slate-700' : 'text-emerald-400 bg-emerald-500/10 border border-emerald-500/30' ?> px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5">
<?= svgIcon($isPaidMobile ? 'refresh' : 'check') ?>
                                    <?= $isPaidMobile ? 'Mark Unpaid' : 'Mark Paid' ?>
                                </a>
<a href="edit.php?id=<?= $expense["id"] ?>" data-modal-uri="edit.php?id=<?= $expense["id"] ?>" class="bg-slate-800 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700 inline-flex items-center gap-1.5"><?= svgIcon('edit') ?> Edit</a>
                                <a href="delete.php?id=<?= $expense["id"] ?><?= getCsrfQueryParam() ?>" onclick="return confirm('Delete this record?')" class="text-rose-400 bg-rose-500/10 border border-rose-500/30 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5"><?= svgIcon('trash') ?> Delete</a>
                                <?php if ($isRecurringSeries): ?>
                                    <a href="delete.php?id=<?= $expense["id"] ?>&mode=all<?= getCsrfQueryParam() ?>" onclick="return confirm('Delete this record AND all other entries in this recurring series?')" class="text-rose-400 bg-rose-500/10 border border-rose-500/30 px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5"><?= svgIcon('trash') ?> Series</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 mt-6">
                        <p class="text-slate-400 text-sm">
                            Page <?= $currentPage ?> of <?= $totalPages ?>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?= $currentPage - 1 ?>&<?= $queryString ?>"
                                    class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                    <?= svgIcon('chevron-left') ?> Prev
                                </a>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>&<?= $queryString ?>"
                                    class="<?= $i == $currentPage ? 'bg-indigo-600 text-white' : 'bg-slate-700 hover:bg-slate-600 text-white' ?> text-sm px-4 py-2 rounded-lg transition-colors">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?= $currentPage + 1 ?>&<?= $queryString ?>"
                                    class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors inline-flex items-center gap-1.5">
                                    Next <?= svgIcon('chevron-right') ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

<script>
        flatpickr("#dateFrom", { dateFormat: "Y-m-d", allowInput: true, animate: true });
        flatpickr("#dateTo", { dateFormat: "Y-m-d", allowInput: true, animate: true });

// Toggle record status via AJAX so the page updates in place (no reload/jump).
        document.addEventListener("click", function(e) {
            var link = e.target.closest ? e.target.closest("[data-mark-toggle]") : null;
            if (!link) return;
            e.preventDefault();

            // Disable the link while the request is in flight.
            link.style.pointerEvents = "none";
            link.style.opacity = "0.5";

            fetch(link.getAttribute("href"), {
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(function(r) { return r.json(); })
.then(function(data) {
                if (!data.success) return;
                var type = data.type || "expense";
                var paid  = !!data.paid;
                var row   = link.closest("[data-status-row]");

                // Show a toast notification for the status change.
                var notice = (type === "income") ? (paid ? "Marked as received" : "Marked as pending") : (paid ? "Marked as paid" : "Marked as unpaid");
                showToast(notice, "success");

                if (row) {
                    // Update the status badge in place.
                    var badge = row.querySelector("[data-status-badge]");
                    if (badge) {
                        var label = (type === "income") ? (paid ? "Received" : "Pending") : (paid ? "Paid" : "Unpaid");
                        badge.textContent = label;
                        badge.className = "text-xs px-2.5 py-1 rounded-full font-medium " +
                            (paid ? "bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"
                                   : "bg-amber-500/10 text-amber-400 border border-amber-500/20");
                    }
// Update the row's opacity to reflect paid/unpaid.
                    row.classList.toggle("opacity-60", paid);

                    // Rebuild the href to flip the toggle for the next click.
                    var base = link.getAttribute("href").split("unpaid=")[0];
                    link.setAttribute("href", base + (paid ? "unpaid=1" : "") );

                    // Mobile button uses an icon + "Mark Paid"/"Mark Unpaid" text.
                    var icon = link.querySelector("svg");
                    if (icon) {
                        // Preserve the icon but swap its inner path to check/refresh.
                        link.innerHTML =
                            (paid
                                ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> '
                                : '<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg> ') +
                            (paid ? "Mark Unpaid" : "Mark Paid");
                        var paidCls = "text-slate-400 bg-slate-800 border border-slate-700";
                        var unpaidCls = "text-emerald-400 bg-emerald-500/10 border border-emerald-500/30";
                        link.className = (paid ? paidCls : unpaidCls) + " px-3 py-1.5 rounded-lg inline-flex items-center gap-1.5";
                    } else {
                        // Desktop shows a plain "Mark"/"Undo" text link.
                        link.textContent = paid ? "Undo" : "Mark";
                        link.classList.toggle("text-indigo-400", !paid);
                        link.classList.toggle("hover:text-indigo-300", !paid);
                    }
                }

                link.style.pointerEvents = "";
                link.style.opacity = "";
            })
            .catch(function() {
                link.style.pointerEvents = "";
                link.style.opacity = "";
            });
        });
    </script>

<?php renderFooter(); ?>

