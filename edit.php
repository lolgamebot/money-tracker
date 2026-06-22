<?php
session_start();
require "db.php";
require "helpers.php";
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
        $updateExpense = $pdo->prepare("UPDATE expenses SET amount=?, category_id=?, type=?, description=?, date=? WHERE id=? AND user_id=?");
        $updateExpense->execute([$amount, $categoryId, $type, $description, $date, $expenseId, $userId]);
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
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Edit Record</h1>

        <?php if (isset($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-lg px-4 py-3 mb-6 text-sm"><?= $error ?></div>
        <?php endif; ?>

        <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
            <form action="edit.php?id=<?= $expenseId ?>" method="POST" class="space-y-5">
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
                    <input type="date" name="date"
                        value="<?= $expense['date'] ?>" required
                        class="w-full bg-[#0a0f1e] border border-slate-700 rounded-lg px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
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

</body>
</html>