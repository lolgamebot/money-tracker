<?php
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

$categories = getCategories($pdo, $userId);

$errors     = [];
$successes  = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $amount             = $_POST["amount"] ?? "";
    $categoryId         = $_POST["category_id"] ?? "";
    $description        = $_POST["description"] ?? "";
    $date               = $_POST["date"] ?? "";
    $type               = $_POST["type"] ?? "expense";
    $isRecurring        = isset($_POST["is_recurring"]) ? 1 : 0;
    $recurringInterval  = $isRecurring ? ($_POST["recurring_interval"] ?? "monthly") : null;
    $recurringEndDate   = null;

    if ($isRecurring) {
        $endCondition  = $_POST["end_condition"] ?? "infinite";
        $recurringEndDate = computeRecurringEndDate($date, $recurringInterval, $endCondition, $_POST);
    }

    if (empty($amount) || empty($categoryId) || empty($date) || empty($type)) {
        $errors[] = "Please fill all required fields!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount!";
    } else {
        $createExpense = $pdo->prepare("
            INSERT INTO expenses
            (user_id, category_id, amount, type, description, date, is_recurring, recurring_interval, recurring_duration, recurring_end_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
$createExpense->execute([
            $userId, $categoryId, $amount, $type, $description, $date,
            $isRecurring, $recurringInterval, null, $recurringEndDate
        ]);
        setFlash("Record added successfully!");
        if (isAjaxRequest()) {
            respondJson(['success' => true]);
        }
        header("Location: add.php");
        exit;
    }
}

$defaultDate = $date ?? date('Y-m-d');
$modal = isset($_GET["modal"]) || isAjaxRequest();
?>

<?php if ($modal): ?>
<title data-modal-title>Add Record</title>
<?php endif; ?>

<?php if (!$modal): ?>
<?php renderHeader('Add Record', ['flatpickr' => true]); ?>

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6">Add Record</h1>
<?php else: ?>
    <div>
<?php endif; ?>

        <?php renderAlerts($errors, []); ?>
        <?php renderFlash(); ?>

        <?php if (count($categories) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-6 text-center">
                <p class="text-slate-400 mb-3">You need at least one category before adding a record.</p>
                <a href="categories.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Create a Category</a>
            </div>
        <?php else: ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
                <form action="add.php" method="POST" class="space-y-5">
                    <?php renderCsrfInput(); ?>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                        <select name="type" required class="<?= INPUT_CLASS ?>">
                            <option value="expense" <?= ($type ?? '') == 'expense' ? 'selected' : '' ?>>Expense</option>
                            <option value="income" <?= ($type ?? '') == 'income' ? 'selected' : '' ?>>Income</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Amount</label>
                        <input type="number" name="amount" step="0.01" min="0.01" required class="<?= INPUT_CLASS ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Category</label>
                        <select name="category_id" required class="<?= INPUT_CLASS ?>">
                            <option value="">-- Select a category --</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category["id"] ?>"><?= e($category["name"]) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Description <span class="text-slate-500">(optional)</span></label>
                        <input type="text" name="description" placeholder="What was this for?" class="<?= INPUT_CLASS ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-400 mb-1">Date</label>
                        <div class="relative cursor-pointer">
                            <input type="text" id="datePicker" name="date" required placeholder="YYYY-MM-DD"
                                value="<?= e($defaultDate) ?>"
                                class="<?= INPUT_CLASS ?> cursor-pointer pr-10">
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-indigo-400">
                                <?= svgIcon('calendar', 'h-5 w-5') ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recurring Toggle & Options -->
                    <div class="border border-slate-700 rounded-xl p-4 bg-[#0d1322]">
                        <label class="flex items-center gap-3 cursor-pointer mb-1">
                            <input type="checkbox" name="is_recurring" id="isRecurring"
                                class="w-4 h-4 accent-indigo-600 rounded">
                            <span class="text-slate-200 font-semibold text-sm">Make this a recurring record</span>
                        </label>
                        <p class="text-slate-400 text-xs pl-7 mb-3">Automatically repeats this expense/income on a schedule.</p>

                        <div id="recurringOptions" class="space-y-4 hidden pt-3 border-t border-slate-700/60">
                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Repeat Frequency</label>
                                <select name="recurring_interval" id="recurringInterval" class="<?= INPUT_CLASS ?>">
                                    <option value="daily">Daily (Every day)</option>
                                    <option value="weekly">Weekly (Every week)</option>
                                    <option value="monthly" selected>Monthly (Every month)</option>
                                    <option value="yearly">Yearly (Every year)</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">End Condition</label>
                                <select name="end_condition" id="endCondition" class="<?= INPUT_CLASS ?>">
                                    <option value="infinite">No End Date (Infinite)</option>
                                    <option value="date">End on a specific date</option>
                                    <option value="occurrences">End after a specific number of times</option>
                                    <option value="period">End after a custom duration</option>
                                </select>
                            </div>

                            <!-- End Date Picker -->
                            <div id="endDateBox" class="hidden">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">End Date</label>
                                <div class="relative cursor-pointer">
                                    <input type="text" id="recurringEndDate" name="recurring_end_date" placeholder="YYYY-MM-DD"
                                        class="<?= INPUT_CLASS ?> cursor-pointer pr-10">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-indigo-400">
                                        <?= svgIcon('calendar', 'h-4 w-4') ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Occurrences Count -->
                            <div id="occurrencesBox" class="hidden">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Total Times to Repeat</label>
                                <input type="number" name="occurrences_count" min="1" value="12" placeholder="e.g. 6" class="<?= INPUT_CLASS ?>">
                                <p class="text-slate-500 text-xs mt-1">e.g. Entering 6 creates 6 total entries across the repeat interval.</p>
                            </div>

                            <!-- Period Duration -->
                            <div id="periodBox" class="hidden">
                                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Duration Period</label>
                                <div class="flex gap-2">
                                    <input type="number" name="period_num" min="1" value="6" placeholder="e.g. 6"
                                        class="<?= INPUT_CLASS ?>">
                                    <select name="period_unit" class="<?= INPUT_CLASS ?>">
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
        flatpickr("#datePicker", {
            dateFormat: "Y-m-d",
            defaultDate: "<?= e($defaultDate) ?>",
            allowInput: true,
            animate: true
        });

        flatpickr("#recurringEndDate", {
            dateFormat: "Y-m-d",
            allowInput: true,
            animate: true
        });

        // Recurring toggle
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
</script>

<?php if (!$modal): ?>
<?php renderFooter(); ?>
<?php endif; ?>

