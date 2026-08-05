<?php
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = getUserId();

if (!isset($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$expenseId = (int)$_GET["id"];

$getExpense = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
$getExpense->execute([$expenseId, $userId]);
$expense = $getExpense->fetch();

if (!$expense) {
    header("Location: index.php");
    exit;
}

$categories = getCategories($pdo, $userId);
$errors     = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    verifyCsrfToken();

    $amount            = $_POST["amount"] ?? "";
    $categoryId        = $_POST["category_id"] ?? "";
    $description       = $_POST["description"] ?? "";
    $date              = $_POST["date"] ?? "";
    $type              = $_POST["type"] ?? "expense";

    if (empty($amount) || empty($categoryId) || empty($date) || empty($type)) {
        $errors[] = "Please fill all required fields!";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid amount!";
    } else {
        $isRecurring       = isset($_POST["is_recurring"]) ? 1 : 0;
        $recurringInterval = $isRecurring ? ($_POST["recurring_interval"] ?? $expense["recurring_interval"]) : null;
        $recurringEndDate  = null;

        if ($isRecurring) {
            $endCondition = $_POST["end_condition"] ?? "infinite";
            $recurringEndDate = computeRecurringEndDate($date, $recurringInterval, $endCondition, $_POST);
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

setFlash("Record updated successfully!");
        if (isAjaxRequest()) {
            respondJson(['success' => true]);
        }
        header("Location: index.php");
        exit;
    }
}

$modal = isset($_GET["modal"]) || isAjaxRequest();
?>

<?php if ($modal): ?>
<title data-modal-title>Edit Record</title>
<?php endif; ?>

<?php if (!$modal): ?>
<?php renderHeader('Edit Record', ['flatpickr' => true]); ?>

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-4 sm:px-6 py-6 sm:py-8 w-full">
        <h1 class="text-2xl font-bold text-white mb-6">Edit Record</h1>
<?php else: ?>
    <div>
<?php endif; ?>

        <?php renderAlerts($errors, []); ?>

        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <form action="edit.php?id=<?= $expenseId ?>" method="POST" class="space-y-5">
                <?php renderCsrfInput(); ?>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Type</label>
                    <select name="type" required class="<?= INPUT_CLASS ?>">
                        <option value="expense" <?= $expense["type"] == "expense" ? "selected" : "" ?>>Expense</option>
                        <option value="income" <?= $expense["type"] == "income" ? "selected" : "" ?>>Income</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Amount</label>
                    <input type="number" name="amount" step="0.01" min="0.01"
                        value="<?= e($expense['amount']) ?>" required
                        class="<?= INPUT_CLASS ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Category</label>
                    <select name="category_id" required class="<?= INPUT_CLASS ?>">
                        <option value="">-- Select a category --</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category["id"] ?>"
                                <?= $category["id"] == $expense["category_id"] ? "selected" : "" ?>>
                                <?= e($category["name"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Description <span class="text-slate-500">(optional)</span></label>
                    <input type="text" name="description"
                        value="<?= e($expense['description']) ?>"
                        placeholder="What was this for?"
                        class="<?= INPUT_CLASS ?>">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-400 mb-1">Date</label>
                    <div class="relative cursor-pointer">
                        <input type="text" id="datePicker" name="date"
                            value="<?= e($expense['date']) ?>" required placeholder="YYYY-MM-DD"
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
                            <?= $expense["is_recurring"] ? "checked" : "" ?>
                            class="w-4 h-4 accent-indigo-600 rounded">
                        <span class="text-slate-200 font-semibold text-sm">This is a recurring record</span>
                    </label>
                    <p class="text-slate-400 text-xs pl-7 mb-3">Automatically repeats this expense/income on a schedule.</p>

                    <div id="recurringOptions" class="space-y-4 <?= $expense["is_recurring"] ? "" : "hidden" ?> pt-3 border-t border-slate-700/60">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">Repeat Frequency</label>
                            <select name="recurring_interval" id="recurringInterval" class="<?= INPUT_CLASS ?>">
                                <option value="daily" <?= $expense["recurring_interval"] == "daily" ? "selected" : "" ?>>Daily (Every day)</option>
                                <option value="weekly" <?= $expense["recurring_interval"] == "weekly" ? "selected" : "" ?>>Weekly (Every week)</option>
                                <option value="monthly" <?= ($expense["recurring_interval"] == "monthly" || empty($expense["recurring_interval"])) ? "selected" : "" ?>>Monthly (Every month)</option>
                                <option value="yearly" <?= $expense["recurring_interval"] == "yearly" ? "selected" : "" ?>>Yearly (Every year)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">End Condition</label>
                            <select name="end_condition" id="endCondition" class="<?= INPUT_CLASS ?>">
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
                                    value="<?= e($expense["recurring_end_date"] ?? '') ?>"
                                    placeholder="YYYY-MM-DD"
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

