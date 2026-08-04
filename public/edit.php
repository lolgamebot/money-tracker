<?php
session_start();
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

if (!isset($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$expenseId = $_GET["id"];

$getExpense = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
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
    verifyCsrfToken();
    $amount = $_POST["amount"];
    $categoryId = $_POST["category_id"];
    $description = $_POST["description"];
    $date = $_POST["date"];
    $type = $_POST["type"];

    if (empty($amount) || empty($categoryId) || empty($date) || empty($type)) {
        $error = "Please fill all required fields!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount!";
    } else {
        $isRecurring = isset($_POST["is_recurring"]) ? 1 : 0;
        $recurringInterval = $isRecurring ? ($_POST["recurring_interval"] ?? $expense["recurring_interval"]) : null;
        $recurringEndDate = null;

        if ($isRecurring) {
            $endCondition = $_POST["end_condition"] ?? "infinite";
            $startDate = new DateTime($date);

            if ($endCondition === "date" && !empty($_POST["recurring_end_date"])) {
                $recurringEndDate = $_POST["recurring_end_date"];
            } elseif ($endCondition === "occurrences") {
                $count = max(1, (int)($_POST["occurrences_count"] ?? 1));
                $step = $count - 1;
                $endDate = clone $startDate;
                if ($step > 0) {
                    switch ($recurringInterval) {
                        case 'daily': $endDate->modify("+$step days"); break;
                        case 'weekly': $endDate->modify("+$step weeks"); break;
                        case 'monthly': $endDate->modify("+$step months"); break;
                        case 'yearly': $endDate->modify("+$step years"); break;
                    }
                }
                $recurringEndDate = $endDate->format('Y-m-d');
            } elseif ($endCondition === "period") {
                $num = max(1, (int)($_POST["period_num"] ?? 1));
                $unit = $_POST["period_unit"] ?? "months";
                $endDate = clone $startDate;
                $endDate->modify("+$num $unit");
                $recurringEndDate = $endDate->format('Y-m-d');
            }
        }

        $updateExpense = $pdo->prepare("
            UPDATE expenses 
            SET amount=?, category_id=?, type=?, description=?, date=?, is_recurring=?, recurring_interval=?, recurring_end_date=? 
            WHERE id=? AND user_id=?
        ");
        $updateExpense->execute([
            $amount, $categoryId, $type, $description, $date, 
            $isRecurring, $recurringInterval, $recurringEndDate, 
            $expenseId, $userId
        ]);

        // Propagate amount/category/type/description changes to already-generated
        // child records, keeping each child's own date untouched.
        $updateChildren = $pdo->prepare("
            UPDATE expenses
            SET amount=?, category_id=?, type=?, description=?
            WHERE parent_id=? AND user_id=?
        ");
        $updateChildren->execute([
            $amount, $categoryId, $type, $description,
            $expenseId, $userId
        ]);

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
    <title>Edit Record - Money Tracker</title>
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
            max-width: 90vw !important;
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

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6">Edit Record</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <form action="edit.php?id=<?= $expenseId ?>" method="POST" class="space-y-5">
                <?php renderCsrfInput(); ?>
                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                    <select name="type" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="expense" <?= $expense["type"] == "expense" ? "selected" : "" ?>>Expense</option>
                        <option value="income" <?= $expense["type"] == "income" ? "selected" : "" ?>>Income</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                        value="<?= $expense['amount'] ?>" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Category</label>
                    <select name="category_id" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                        <option value="">-- Select a category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category["id"] ?>"
                                <?= $category["id"] == $expense["category_id"] ? "selected" : "" ?>>
                                <?= $category["name"] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Description <span class="text-slate-500">(optional)</span></label>
                    <input type="text" name="description"
                        value="<?= $expense['description'] ?>"
                        placeholder="What was this for?"
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Date</label>
                    <div class="relative cursor-pointer">
                        <input type="text" id="datePicker" name="date"
                            value="<?= htmlspecialchars($expense['date']) ?>" required placeholder="YYYY-MM-DD"
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg pl-4 pr-10 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 cursor-pointer">
                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-indigo-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Recurring Toggle & Options -->
                <div class="border border-slate-700 rounded-xl p-4 bg-[#0d1322]">
                    <label class="flex items-center gap-3 cursor-pointer mb-1">
                        <input type="checkbox" name="is_recurring" id="isRecurring"
                            <?= $expense["is_recurring"] ? "checked" : "" ?>
                            class="w-4 h-4 accent-indigo-600 rounded">
                        <span class="text-slate-200 font-semibold text-sm">This is a recurring record</span>
                    </label>
                    <p class="text-slate-400 text-xs pl-7 mb-3">Automatically repeats this expense/income on a schedule.</p>

                    <div id="recurringOptions" class="space-y-4 <?= $expense["is_recurring"] ? "" : "hidden" ?> pt-3 border-t border-slate-700/60">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Repeat Frequency</label>
                            <select name="recurring_interval" id="recurringInterval"
                                class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                                <option value="daily" <?= $expense["recurring_interval"] == "daily" ? "selected" : "" ?>>Daily (Every day)</option>
                                <option value="weekly" <?= $expense["recurring_interval"] == "weekly" ? "selected" : "" ?>>Weekly (Every week)</option>
                                <option value="monthly" <?= ($expense["recurring_interval"] == "monthly" || empty($expense["recurring_interval"])) ? "selected" : "" ?>>Monthly (Every month)</option>
                                <option value="yearly" <?= $expense["recurring_interval"] == "yearly" ? "selected" : "" ?>>Yearly (Every year)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">End Condition</label>
                            <select name="end_condition" id="endCondition"
                                class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                                <option value="infinite" <?= empty($expense["recurring_end_date"]) ? "selected" : "" ?>>No End Date (Infinite)</option>
                                <option value="date" <?= !empty($expense["recurring_end_date"]) ? "selected" : "" ?>>End on a specific date</option>
                                <option value="occurrences">End after a specific number of times</option>
                                <option value="period">End after a custom duration</option>
                            </select>
                        </div>

                        <!-- End Date Picker -->
                        <div id="endDateBox" class="<?= !empty($expense["recurring_end_date"]) ? "" : "hidden" ?>">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">End Date</label>
                            <div class="relative cursor-pointer">
                                <input type="text" id="recurringEndDate" name="recurring_end_date"
                                    value="<?= htmlspecialchars($expense["recurring_end_date"] ?? '') ?>"
                                    placeholder="YYYY-MM-DD"
                                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg pl-4 pr-10 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm cursor-pointer">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-indigo-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Occurrences Count -->
                        <div id="occurrencesBox" class="hidden">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Total Times to Repeat</label>
                            <input type="number" name="occurrences_count" min="1" value="12" placeholder="e.g. 6"
                                class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                        </div>

                        <!-- Period Duration -->
                        <div id="periodBox" class="hidden">
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Duration Period</label>
                            <div class="flex gap-2">
                                <input type="number" name="period_num" min="1" value="6" placeholder="e.g. 6"
                                    class="flex-1 bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                                <select name="period_unit"
                                    class="bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm">
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months" selected>Months</option>
                                    <option value="years">Years</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit"
                        class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors">
                        Update Record
                    </button>
                    <a href="index.php"
                        class="flex-1 text-center bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2.5 rounded-lg transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            allowInput: true,
            animate: true
        });

        const recurringCheckbox = document.getElementById('isRecurring');
        const recurringOptions = document.getElementById('recurringOptions');
        const endCondition = document.getElementById('endCondition');
        const endDateBox = document.getElementById('endDateBox');
        const occurrencesBox = document.getElementById('occurrencesBox');
        const periodBox = document.getElementById('periodBox');

        if (recurringCheckbox) {
            recurringCheckbox.addEventListener('change', function() {
                recurringOptions.classList.toggle('hidden', !this.checked);
            });
        }

        if (endCondition) {
            endCondition.addEventListener('change', function() {
                endDateBox.classList.add('hidden');
                occurrencesBox.classList.add('hidden');
                periodBox.classList.add('hidden');

                if (this.value === 'date') endDateBox.classList.remove('hidden');
                else if (this.value === 'occurrences') occurrencesBox.classList.remove('hidden');
                else if (this.value === 'period') periodBox.classList.remove('hidden');
            });
        }

        flatpickr("#recurringEndDate", {
            dateFormat: "Y-m-d",
            allowInput: true,
            animate: true
        });
    </script>
</body>
</html>