<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

require "process_recurring.php";
processRecurring($pdo, $userId);

// Filter values
$search = $_GET["search"] ?? "";
$filterType = $_GET["type"] ?? "";
$filterCategory = $_GET["category"] ?? "";
$filterDateFrom = $_GET["date_from"] ?? "";
$filterDateTo = $_GET["date_to"] ?? "";

// Pagination
$perPage = 10;
$currentPage = isset($_GET["page"]) ? (int)$_GET["page"] : 1;
if ($currentPage < 1) $currentPage = 1;
$offset = ($currentPage - 1) * $perPage;

// Build base filter conditions
$conditions = "WHERE expenses.user_id = ?";
$params = [$userId];

if (!empty($search)) {
    $conditions .= " AND expenses.description LIKE ?";
    $params[] = "%" . $search . "%";
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

// Count total records for pagination
$countQuery = $pdo->prepare("SELECT COUNT(*) FROM expenses LEFT JOIN categories ON expenses.category_id = categories.id $conditions");
$countQuery->execute($params);
$totalRecords = $countQuery->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

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

// Summary totals
$getTotalIncome = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ? AND type = 'income'");
$getTotalIncome->execute([$userId]);
$totalIncome = $getTotalIncome->fetch()["total"] ?? 0;

$getTotalExpense = $pdo->prepare("SELECT SUM(amount) AS total FROM expenses WHERE user_id = ? AND type = 'expense'");
$getTotalExpense->execute([$userId]);
$totalExpense = $getTotalExpense->fetch()["total"] ?? 0;

$balance = $totalIncome - $totalExpense;

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

$getMonthTotal = $pdo->prepare("
    SELECT SUM(amount) AS total
    FROM expenses
    WHERE user_id = ? AND type = 'expense'
    AND MONTH(date) = MONTH(CURRENT_DATE())
    AND YEAR(date) = YEAR(CURRENT_DATE())
");
$getMonthTotal->execute([$userId]);
$monthTotal = $getMonthTotal->fetch()["total"] ?? 0;

$getCategories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
$getCategories->execute([$userId]);
$categories = $getCategories->fetchAll();

// Build query string for pagination links
$queryParams = array_filter([
    "search" => $search,
    "type" => $filterType,
    "category" => $filterCategory,
    "date_from" => $filterDateFrom,
    "date_to" => $filterDateTo,
]);
$queryString = http_build_query($queryParams);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        .flatpickr-calendar {
            background: #111827 !important;
            border: 1px solid #334155 !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5) !important;
            border-radius: 0.75rem !important;
            font-family: inherit !important;
        }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected.inRange, .flatpickr-day.selected:focus, .flatpickr-day.selected:hover, .flatpickr-day.selected.prevMonthDay, .flatpickr-day.selected.nextMonthDay {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
        }
        .flatpickr-day:hover {
            background: #1e293b !important;
        }
        .flatpickr-day.today {
            border-color: #6366f1 !important;
        }
        .flatpickr-current-month .flatpickr-monthDropdown-months,
        .flatpickr-current-month input.cur-year {
            font-weight: 600 !important;
        }
        .flatpickr-calendar.arrowTop:before { border-bottom-color: #334155 !important; }
        .flatpickr-calendar.arrowTop:after { border-bottom-color: #111827 !important; }
        .flatpickr-calendar.arrowBottom:before { border-top-color: #334155 !important; }
        .flatpickr-calendar.arrowBottom:after { border-top-color: #111827 !important; }
    </style>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Welcome back, <?= $_SESSION["username"] ?>!</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-8">
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <p class="text-slate-400 text-xs sm:text-sm mb-1">Total Income</p>
                <p class="text-xl sm:text-2xl font-bold text-emerald-400">₱<?= number_format($totalIncome, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <p class="text-slate-400 text-xs sm:text-sm mb-1">Total Expenses</p>
                <p class="text-xl sm:text-2xl font-bold text-rose-400">₱<?= number_format($totalExpense, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <p class="text-slate-400 text-xs sm:text-sm mb-1">Balance</p>
                <p class="text-xl sm:text-2xl font-bold <?= $balance >= 0 ? 'text-white' : 'text-rose-400' ?>">
                    ₱<?= number_format($balance, 2) ?>
                </p>
            </div>
            <div class="bg-[#111827] rounded-xl p-4 sm:p-5 border border-slate-700">
                <p class="text-slate-400 text-xs sm:text-sm mb-1">This Month</p>
                <p class="text-xl sm:text-2xl font-bold text-indigo-400">₱<?= number_format($monthTotal, 2) ?></p>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Spending by Category</h2>
            <?php if (count($categoryTotals) === 0): ?>
                <p class="text-slate-400 text-sm">No expense data yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($categoryTotals as $cat): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                            <span class="text-slate-300 text-sm"><?= $cat["name"] ?></span>
                            <span class="text-rose-400 font-semibold text-sm">₱<?= number_format($cat["total"], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Records List -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-4 sm:p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">All Records</h2>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs sm:text-sm font-medium px-3 sm:px-4 py-2 rounded-lg transition-colors">+ Add Record</a>
            </div>

            <!-- Search and Filter -->
            <form action="index.php" method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                <input type="text" name="search" placeholder="Search description..."
                    value="<?= htmlspecialchars($search) ?>"
                    class="bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm">

                <select name="type"
                    class="bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                    <option value="">All Types</option>
                    <option value="income" <?= $filterType == "income" ? "selected" : "" ?>>Income</option>
                    <option value="expense" <?= $filterType == "expense" ? "selected" : "" ?>>Expense</option>
                </select>

                <select name="category"
                    class="bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category["id"] ?>" <?= $filterCategory == $category["id"] ? "selected" : "" ?>>
                            <?= $category["name"] ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="flex gap-2 items-center">
                    <label class="text-slate-400 text-sm whitespace-nowrap">From:</label>
                    <div class="relative w-full cursor-pointer">
                        <input type="text" id="dateFrom" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>" placeholder="YYYY-MM-DD"
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg pl-3 pr-8 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 text-sm cursor-pointer">
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 items-center">
                    <label class="text-slate-400 text-sm whitespace-nowrap">To:</label>
                    <div class="relative w-full cursor-pointer">
                        <input type="text" id="dateTo" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>" placeholder="YYYY-MM-DD"
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg pl-3 pr-8 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 text-sm cursor-pointer">
                        <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium py-2.5 rounded-lg transition-colors">
                        Filter
                    </button>
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
                <?= (!empty($search) || !empty($filterType) || !empty($filterCategory) || !empty($filterDateFrom) || !empty($filterDateTo)) ? '— <a href="index.php" class="text-indigo-400 hover:underline">Clear filters</a>' : '' ?>
            </p>

            <?php if (count($expenses) === 0): ?>
                <p class="text-slate-400 text-sm">No records found.</p>
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
                                <th class="text-right py-2.5 whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($expenses as $expense): ?>
                                <?php $isRecurringSeries = $expense['is_recurring'] || !empty($expense['parent_id']); ?>
                                <tr class="hover:bg-slate-800/30 transition-colors">
                                    <td class="py-3 pr-4 text-slate-400 whitespace-nowrap"><?= $expense["date"] ?></td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="text-xs px-2.5 py-1 rounded-full <?= $expense['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                            <?= ucfirst($expense['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 whitespace-nowrap">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2.5 py-1 rounded-full border border-indigo-500/20">
                                            <?= htmlspecialchars($expense["category_name"]) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-300 max-w-[180px] truncate">
                                        <?= htmlspecialchars($expense["description"] ?: "—") ?>
                                        <?php if ($isRecurringSeries): ?>
                                            <span class="inline-flex items-center text-[10px] bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded ml-1 font-medium" title="Recurring record">
                                                🔄 Recurring
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-3 pr-4 text-right font-semibold whitespace-nowrap <?= $expense['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= $expense['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($expense["amount"], 2) ?>
                                    </td>
                                    <td class="py-3 text-right whitespace-nowrap">
                                        <a href="edit.php?id=<?= $expense["id"] ?>" class="text-slate-400 hover:text-white mr-2.5 transition-colors text-xs">Edit</a>
                                        <a href="delete.php?id=<?= $expense["id"] ?>"
                                           onclick="return confirm('Delete this record?')"
                                           class="text-rose-400 hover:text-rose-300 transition-colors text-xs">Delete</a>
                                        <?php if ($isRecurringSeries): ?>
                                            <a href="delete.php?id=<?= $expense["id"] ?>&mode=all"
                                               onclick="return confirm('Delete this record AND all other entries in this recurring series from your dashboard?')"
                                               class="text-rose-400 hover:text-rose-300 transition-colors text-xs bg-rose-500/10 border border-rose-500/30 px-2 py-1 rounded ml-2">Delete Series</a>
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
                        <div class="bg-[#0a0f1e] border border-slate-700/80 rounded-xl p-4 space-y-2.5">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-white text-base">
                                        <?= htmlspecialchars($expense["description"] ?: ($expense["category_name"] . " " . ucfirst($expense["type"]))) ?>
                                    </p>
                                    <p class="text-xs text-slate-400 mt-0.5"><?= $expense["date"] ?></p>
                                </div>
                                <span class="font-bold text-base <?= $expense['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= $expense['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($expense["amount"], 2) ?>
                                </span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-800">
                                <span class="text-xs px-2.5 py-0.5 rounded-full <?= $expense['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                    <?= ucfirst($expense['type']) ?>
                                </span>
                                <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2.5 py-0.5 rounded-full border border-indigo-500/20">
                                    <?= htmlspecialchars($expense["category_name"]) ?>
                                </span>
                                <?php if ($isRecurringSeries): ?>
                                    <span class="text-[10px] bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded font-medium">
                                        🔄 Recurring
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center justify-end gap-3 pt-2 text-xs border-t border-slate-800/80">
                                <a href="edit.php?id=<?= $expense["id"] ?>" class="bg-slate-800 text-slate-300 hover:text-white px-3 py-1.5 rounded-lg border border-slate-700">Edit</a>
                                <a href="delete.php?id=<?= $expense["id"] ?>" onclick="return confirm('Delete this record?')" class="text-rose-400 bg-rose-500/10 border border-rose-500/30 px-3 py-1.5 rounded-lg">Delete</a>
                                <?php if ($isRecurringSeries): ?>
                                    <a href="delete.php?id=<?= $expense["id"] ?>&mode=all" onclick="return confirm('Delete this record AND all other entries in this recurring series?')" class="text-rose-400 bg-rose-500/10 border border-rose-500/30 px-3 py-1.5 rounded-lg">Delete Series</a>
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
                                    class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                                    ← Prev
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
                                    class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                                    Next →
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
    </script>
</body>
</html>