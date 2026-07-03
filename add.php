<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

$getCategories = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY name ASC");
$getCategories->execute([$userId]);
$categories = $getCategories->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = $_POST["amount"];
    $categoryId = $_POST["category_id"];
    $description = $_POST["description"];
    $date = $_POST["date"];
    $type = $_POST["type"];
    $isRecurring = isset($_POST["is_recurring"]) ? 1 : 0;
    $recurringInterval = $isRecurring ? $_POST["recurring_interval"] : null;
    $durationType = $isRecurring ? $_POST["duration_type"] : null;
    $recurringDuration = null;
    $recurringEndDate = null;

    if ($isRecurring && $durationType !== "infinite") {
        $startDate = new DateTime($date);

        if ($durationType === "preset") {
            $recurringDuration = (int)$_POST["recurring_duration_preset"];
            $endDate = clone $startDate;
            $endDate->modify("+$recurringDuration months");
            $recurringEndDate = $endDate->format('Y-m-d');

        } elseif ($durationType === "custom") {
            $customAmount = (int)$_POST["recurring_duration_custom"];
            $customUnit = $_POST["recurring_duration_unit"];
            $recurringDuration = $customAmount;
            $endDate = clone $startDate;

            switch ($customUnit) {
                case 'days':
                    $endDate->modify("+$customAmount days");
                    break;
                case 'weeks':
                    $endDate->modify("+$customAmount weeks");
                    break;
                case 'months':
                    $endDate->modify("+$customAmount months");
                    break;
                case 'years':
                    $endDate->modify("+$customAmount years");
                    break;
            }
            $recurringEndDate = $endDate->format('Y-m-d');
        }
    }

    if (empty($amount) || empty($categoryId) || empty($date) || empty($type)) {
        $error = "Please fill all required fields!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Please enter a valid amount!";
    } else {
        $createExpense = $pdo->prepare("
            INSERT INTO expenses 
            (user_id, category_id, amount, type, description, date, is_recurring, recurring_interval, recurring_duration, recurring_end_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $createExpense->execute([
            $userId, $categoryId, $amount, $type, $description, $date,
            $isRecurring, $recurringInterval, $recurringDuration, $recurringEndDate
        ]);
        $success = "Record added successfully!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Record - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Add Record</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <?php if (isset($success)): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $success ?></div>
        <?php endif; ?>

        <?php if (count($categories) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-6 text-center">
                <p class="text-slate-400 mb-3">You need at least one category before adding a record.</p>
                <a href="categories.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Create a Category</a>
            </div>
        <?php else: ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
                <form action="add.php" method="POST" class="space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                        <select name="type" required
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <option value="expense">Expense</option>
                            <option value="income">Income</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Category</label>
                        <select name="category_id" required
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <option value="">-- Select a category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category["id"] ?>"><?= $category["name"] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Description <span class="text-slate-500">(optional)</span></label>
                        <input type="text" name="description" placeholder="What was this for?"
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Date</label>
                        <input type="date" name="date" required
                            class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>

                    <!-- Recurring Toggle -->
                    <div class="border border-slate-700 rounded-lg p-4">
                        <label class="flex items-center gap-3 cursor-pointer mb-4">
                            <input type="checkbox" name="is_recurring" id="isRecurring"
                                class="w-4 h-4 accent-indigo-600">
                            <span class="text-slate-300 font-medium">This is a recurring record</span>
                        </label>

                        <div id="recurringOptions" class="space-y-4 hidden">
                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Repeats Every</label>
                                <select name="recurring_interval"
                                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <option value="daily">Day</option>
                                    <option value="weekly">Week</option>
                                    <option value="monthly" selected>Month</option>
                                    <option value="yearly">Year</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-400 mb-1">Duration</label>
                                <select name="duration_type" id="durationType"
                                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <option value="infinite">Infinite (no end)</option>
                                    <option value="preset">Preset</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </div>

                            <!-- Preset Options -->
                            <div id="presetOptions" class="hidden">
                                <label class="block text-sm font-medium text-slate-400 mb-1">Select Duration</label>
                                <select name="recurring_duration_preset"
                                    class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <option value="1">1 month</option>
                                    <option value="3">3 months</option>
                                    <option value="6">6 months</option>
                                    <option value="12">12 months (1 year)</option>
                                    <option value="24">24 months (2 years)</option>
                                    <option value="36">36 months (3 years)</option>
                                </select>
                            </div>

                            <!-- Custom Options -->
                            <div id="customOptions" class="hidden">
                                <label class="block text-sm font-medium text-slate-400 mb-1">Custom Duration</label>
                                <div class="flex gap-2">
                                    <input type="number" name="recurring_duration_custom" min="1" placeholder="e.g. 15"
                                        class="flex-1 bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    <select name="recurring_duration_unit"
                                        class="bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                        <option value="years">Years</option>
                                    </select>
                                </div>
                                <p class="text-slate-500 text-xs mt-1">Enter any number and pick the unit</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg transition-colors">
                            Add Record
                        </button>
                        <a href="index.php"
                            class="flex-1 text-center bg-slate-700 hover:bg-slate-600 text-white font-semibold py-2.5 rounded-lg transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Recurring toggle
        const recurringCheckbox = document.getElementById('isRecurring');
        const recurringOptions = document.getElementById('recurringOptions');

        recurringCheckbox.addEventListener('change', function() {
            recurringOptions.classList.toggle('hidden', !this.checked);
        });

        // Duration type toggle
        const durationType = document.getElementById('durationType');
        const presetOptions = document.getElementById('presetOptions');
        const customOptions = document.getElementById('customOptions');

        durationType.addEventListener('change', function() {
            presetOptions.classList.add('hidden');
            customOptions.classList.add('hidden');

            if (this.value === 'preset') {
                presetOptions.classList.remove('hidden');
            } else if (this.value === 'custom') {
                customOptions.classList.remove('hidden');
            }
        });
    </script>

</body>
</html>