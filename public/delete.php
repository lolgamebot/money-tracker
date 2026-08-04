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
        // Delete parent template and all generated children in series
        $deleteSeries = $pdo->prepare("DELETE FROM expenses WHERE user_id = ? AND (id = ? OR parent_id = ?)");
        $deleteSeries->execute([$userId, $parentId, $parentId]);
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

header("Location: index.php"); 
exit;