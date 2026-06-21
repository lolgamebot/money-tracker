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

$checkExpense = $pdo->prepare("SELECT * FROM expense WHERE id = ? AND user_id = ?");
$checkExpense->execute([$expenseId, $userId]);
$expense = $checkExpense->fetch();

if (!$expense) {
    header("Location: index.php");
    exit;
}

$deleteExpense = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
$deleteExpense->execute([$expenseId, $userId]);

header("Location: index.php");
exit;