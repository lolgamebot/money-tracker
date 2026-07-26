<?php
function processRecurring($pdo, $userId) {
    // Fetch all active recurring templates for this user
    $getRecurring = $pdo->prepare("
        SELECT * FROM expenses 
        WHERE user_id = ? 
        AND is_recurring = 1
    ");
    $getRecurring->execute([$userId]);
    $recurringRecords = $getRecurring->fetchAll();

    $today = new DateTime('today');

    foreach ($recurringRecords as $record) {
        $interval = $record["recurring_interval"];
        if (empty($interval)) continue;

        $startDate = new DateTime($record["date"]);
        $endDate = $record["recurring_end_date"] ? new DateTime($record["recurring_end_date"]) : null;

        // Cutoff boundary is either the specified end date or today, whichever is earlier
        $cutoffDate = ($endDate && $endDate < $today) ? $endDate : $today;

        // Calculate first generated date after start date
        $nextDate = clone $startDate;

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

        // Generate entries up to the cutoff date
        while ($nextDate <= $cutoffDate) {
            $formattedDate = $nextDate->format('Y-m-d');

            // Check if child entry already exists for this date and parent
            $checkExists = $pdo->prepare("
                SELECT id FROM expenses 
                WHERE user_id = ? 
                AND parent_id = ? 
                AND date = ?
            ");
            $checkExists->execute([$userId, $record["id"], $formattedDate]);
            $exists = $checkExists->fetch();

            if (!$exists) {
                // Insert generated recurring entry
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
                    $formattedDate,
                    $record["id"]
                ]);
            }

            // Advance to next interval
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
                default:
                    break 2;
            }
        }
    }
}
?>