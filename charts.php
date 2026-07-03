<?php
session_start();
require "db.php";
require "helpers.php";
requireLogin();

$userId = $_SESSION["user_id"];

// Spending by category
$getCategoryData = $pdo->prepare("
    SELECT categories.name, SUM(expenses.amount) AS total
    FROM expenses
    LEFT JOIN categories ON expenses.category_id = categories.id
    WHERE expenses.user_id = ? AND expenses.type = 'expense'
    GROUP BY categories.name
    ORDER BY total DESC
");
$getCategoryData->execute([$userId]);
$categoryData = $getCategoryData->fetchAll();

// Monthly spending — last 6 months
$getMonthlyData = $pdo->prepare("
    SELECT 
        DATE_FORMAT(date, '%b %Y') AS month,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) AS expenses,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) AS income
    FROM expenses
    WHERE user_id = ?
    AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY DATE_FORMAT(date, '%Y-%m') ASC
");
$getMonthlyData->execute([$userId]);
$monthlyData = $getMonthlyData->fetchAll();

// Prepare data for Chart.js
$categoryLabels = array_column($categoryData, 'name');
$categoryTotals = array_column($categoryData, 'total');

$monthLabels = array_column($monthlyData, 'month');
$monthlyExpenses = array_column($monthlyData, 'expenses');
$monthlyIncome = array_column($monthlyData, 'income');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charts - Money Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#0a0f1e] min-h-screen text-slate-200">

    <?php renderNav(); ?>

    <div class="max-w-5xl mx-auto px-6 py-8">
        <h1 class="text-2xl font-bold text-white mb-6">Charts & Analytics</h1>

        <?php if (count($categoryData) === 0 && count($monthlyData) === 0): ?>
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-10 text-center">
                <p class="text-slate-400 mb-3">No data yet to display charts.</p>
                <a href="add.php" class="bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">Add your first record</a>
            </div>
        <?php else: ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-6">
                <!-- Pie Chart -->
                <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Spending by Category</h2>
                    <?php if (count($categoryData) === 0): ?>
                        <p class="text-slate-400 text-sm">No expense data yet.</p>
                    <?php else: ?>
                        <canvas id="categoryChart"></canvas>
                    <?php endif; ?>
                </div>

                <!-- Income vs Expense Pie -->
                <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
                    <h2 class="text-lg font-semibold text-white mb-4">Income vs Expenses</h2>
                    <canvas id="incomeExpenseChart"></canvas>
                </div>
            </div>

            <!-- Bar Chart — full width -->
            <div class="bg-[#111827] rounded-xl border border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Last 6 Months Overview</h2>
                <?php if (count($monthlyData) === 0): ?>
                    <p class="text-slate-400 text-sm">No monthly data yet.</p>
                <?php else: ?>
                    <canvas id="monthlyChart"></canvas>
                <?php endif; ?>
            </div>

        <?php endif; ?>
    </div>

    <script>
        const chartDefaults = {
            color: '#94a3b8',
            plugins: {
                legend: {
                    labels: { color: '#94a3b8' }
                }
            }
        };

        // Category Pie Chart
        <?php if (count($categoryData) > 0): ?>
        new Chart(document.getElementById('categoryChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($categoryLabels) ?>,
                datasets: [{
                    data: <?= json_encode($categoryTotals) ?>,
                    backgroundColor: [
                        '#6366f1', '#f43f5e', '#10b981', '#f59e0b',
                        '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6'
                    ],
                    borderColor: '#111827',
                    borderWidth: 2
                }]
            },
            options: {
                ...chartDefaults,
                plugins: {
                    ...chartDefaults.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₱' + context.parsed.toLocaleString('en-PH', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Income vs Expense Pie Chart
        new Chart(document.getElementById('incomeExpenseChart'), {
            type: 'doughnut',
            data: {
                labels: ['Income', 'Expenses'],
                datasets: [{
                    data: [<?= array_sum($monthlyIncome) ?>, <?= array_sum($monthlyExpenses) ?>],
                    backgroundColor: ['#10b981', '#f43f5e'],
                    borderColor: '#111827',
                    borderWidth: 2
                }]
            },
            options: {
                ...chartDefaults,
                plugins: {
                    ...chartDefaults.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₱' + context.parsed.toLocaleString('en-PH', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });

        // Monthly Bar Chart
        <?php if (count($monthlyData) > 0): ?>
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($monthLabels) ?>,
                datasets: [
                    {
                        label: 'Income',
                        data: <?= json_encode($monthlyIncome) ?>,
                        backgroundColor: '#10b98133',
                        borderColor: '#10b981',
                        borderWidth: 2,
                        borderRadius: 6
                    },
                    {
                        label: 'Expenses',
                        data: <?= json_encode($monthlyExpenses) ?>,
                        backgroundColor: '#f43f5e33',
                        borderColor: '#f43f5e',
                        borderWidth: 2,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                ...chartDefaults,
                scales: {
                    x: {
                        ticks: { color: '#94a3b8' },
                        grid: { color: '#1e293b' }
                    },
                    y: {
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        },
                        grid: { color: '#1e293b' }
                    }
                },
                plugins: {
                    ...chartDefaults.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return ' ₱' + context.parsed.y.toLocaleString('en-PH', {minimumFractionDigits: 2});
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>