<?php
function processRecurring($pdo, $userId) {
    $getRecurring = $pdo->prepare("
        SELECT * FROM expenses 
        WHERE user_id = ? 
        AND is_recurring = 1
        AND (recurring_end_date IS NULL OR recurring_end_date >= CURRENT_DATE())
    ");
    $getRecurring->execute([$userId]);
    $recurringRecords = $getRecurring->fetchAll();

    foreach ($recurringRecords as $record) {
        $interval = $record["recurring_interval"];
        $lastDate = new DateTime($record["date"]);
        $today = new DateTime();
        $endDate = $record["recurring_end_date"] ? new DateTime($record["recurring_end_date"]) : null;

        // Calculate next due date from last recorded date
        $nextDate = clone $lastDate;

        switch ($interval) {
            case 'daily':
                $nextDate->modify("+1 day");
                break;
            case 'weekly':
                $nextDate->modify("+1 week");
                break;
            case 'monthly':
                $nextDate->modify("+1 month");
                break;
            case 'yearly':
                $nextDate->modify("+1 year");
                break;
        }

        // Keep generating entries until we catch up to today
        while ($nextDate <= $today) {
            if ($endDate && $nextDate > $endDate) break;

            // Check if entry already exists for this date
            $checkExists = $pdo->prepare("
                SELECT * FROM expenses 
                WHERE user_id = ? 
                AND parent_id = ? 
                AND date = ?
            ");
            $checkExists->execute([$userId, $record["id"], $nextDate->format('Y-m-d')]);
            $exists = $checkExists->fetch();

            if (!$exists) {
                // Insert new entry
                $insertEntry = $pdo->prepare("
                    INSERT INTO expenses 
                    (user_id, category_id, amount, type, description, date, is_recurring, recurring_interval, recurring_end_date, parent_id)
                    VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?)
                ");
                $insertEntry->execute([
                    $userId,
                    $record["category_id"],
                    $record["amount"],
                    $record["type"],
                    $record["description"],
                    $nextDate->format('Y-m-d'),
                    $record["id"]
                ]);
            }

            // Move to next interval
            switch ($interval) {
                case 'daily':
                    $nextDate->modify("+1 day");
                    break;
                case 'weekly':
                    $nextDate->modify("+1 week");
                    break;
                case 'monthly':
                    $nextDate->modify("+1 month");
                    break;
                case 'yearly':
                    $nextDate->modify("+1 year");
                    break;
            }
        }
    }
}
?>