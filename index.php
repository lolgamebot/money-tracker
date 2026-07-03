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
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Welcome back, <?= $_SESSION["username"] ?>!</h1>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">Total Income</p>
                <p class="text-2xl font-bold text-emerald-400">₱<?= number_format($totalIncome, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">Total Expenses</p>
                <p class="text-2xl font-bold text-rose-400">₱<?= number_format($totalExpense, 2) ?></p>
            </div>
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">Balance</p>
                <p class="text-2xl font-bold <?= $balance >= 0 ? 'text-white' : 'text-rose-400' ?>">
                    ₱<?= number_format($balance, 2) ?>
                </p>
            </div>
            <div class="bg-[#111827] rounded-xl p-5 border border-slate-700">
                <p class="text-slate-400 text-sm mb-1">This Month</p>
                <p class="text-2xl font-bold text-indigo-400">₱<?= number_format($monthTotal, 2) ?></p>
            </div>
        </div>

        <!-- Category Breakdown -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-5 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Spending by Category</h2>
            <?php if (count($categoryTotals) === 0): ?>
                <p class="text-slate-400 text-sm">No expense data yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($categoryTotals as $cat): ?>
                        <div class="flex items-center justify-between py-2 border-b border-slate-700 last:border-0">
                            <span class="text-slate-300"><?= $cat["name"] ?></span>
                            <span class="text-rose-400 font-semibold">₱<?= number_format($cat["total"], 2) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Records List -->
        <div class="bg-[#111827] rounded-xl border border-slate-700 p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-white">All Records</h2>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">+ Add Record</a>
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
                    <input type="date" name="date_from" value="<?= $filterDateFrom ?>"
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                </div>

                <div class="flex gap-2 items-center">
                    <label class="text-slate-400 text-sm whitespace-nowrap">To:</label>
                    <input type="date" name="date_to" value="<?= $filterDateTo ?>"
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
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
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-slate-400 border-b border-slate-700">
                                <th class="text-left py-2 pr-4">Date</th>
                                <th class="text-left py-2 pr-4">Type</th>
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
                                        <span class="text-xs px-2 py-1 rounded-full <?= $expense['type'] == 'income' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400' ?>">
                                            <?= ucfirst($expense['type']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4">
                                        <span class="bg-indigo-500/10 text-indigo-400 text-xs px-2 py-1 rounded-full">
                                            <?= $expense["category_name"] ?>
                                        </span>
                                    </td>
                                    <td class="py-3 pr-4 text-slate-300"><?= $expense["description"] ?: "—" ?></td>
                                    <td class="py-3 pr-4 text-right font-semibold <?= $expense['type'] == 'income' ? 'text-emerald-400' : 'text-rose-400' ?>">
                                        <?= $expense['type'] == 'income' ? '+' : '-' ?>₱<?= number_format($expense["amount"], 2) ?>
                                    </td>
                                    <td class="py-3 text-right">
                                        <a href="edit.php?id=<?= $expense["id"] ?>" class="text-slate-400 hover:text-white mr-3 transition-colors">Edit</a>
                                        <a href="delete.php?id=<?= $expense["id"] ?>"
                                           onclick="return confirm('Delete this record?')"
                                           class="text-rose-400 hover:text-rose-300 transition-colors">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="flex items-center justify-between mt-6">
                        <p class="text-slate-400 text-sm">
                            Page <?= $currentPage ?> of <?= $totalPages ?>
                        </p>
                        <div class="flex gap-2">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?= $currentPage - 1 ?>&<?= $queryString ?>"
                                    class="bg-slate-700 hover:bg-slate-600 text-white text-sm px-4 py-2 rounded-lg transition-colors">
                                    ← Previous
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

</body>
</html>