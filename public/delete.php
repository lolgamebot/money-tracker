<?php
require "../config/db.php";
require "../includes/helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

if (!isset($_GET["id"])) {
    header("Location: index.php");
    exit;
}

// Verify CSRF token for all delete actions
verifyCsrfGet();

$expenseId = (int)$_GET["id"];
$mode = $_GET["mode"] ?? "single";

$checkExpense = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
$checkExpense->execute([$expenseId, $userId]);
$expense = $checkExpense->fetch();

if (!$expense) {
    header("Location: index.php");
    exit;
}

if ($mode === "all") {
    // Determine the recurring series parent ID
    $parentId = $expense["is_recurring"] ? $expense["id"] : $expense["parent_id"];

    if ($parentId) {
        // Delete the recurring template AND all its generated child records so
        // the whole series disappears from the dashboard (matches the
        // "Delete this record AND all other entries in this recurring series"
        // confirm text). Without this, orphaned children keep showing up.
        $deleteChildren = $pdo->prepare("DELETE FROM expenses WHERE parent_id = ? AND user_id = ?");
        $deleteChildren->execute([$parentId, $userId]);
        $deleteTemplate = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $deleteTemplate->execute([$parentId, $userId]);
    } else {
        // Fallback to single delete if no parent ID found
        $deleteExpense = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $deleteExpense->execute([$expenseId, $userId]);
    }
} else {
    // Delete only this specific record
    $deleteExpense = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $deleteExpense->execute([$expenseId, $userId]);
}

setFlash("Record deleted.");
header("Location: index.php");
exit;