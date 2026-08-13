<?php
/**
 * processRecurring — generates child entries for active recurring templates.
 *
 * Optimization: instead of running "check exists" + "insert" queries inside
 * the loop for every record (N+1 problem), we batch-load all existing child
 * entries for every active parent in ONE query and reuse a single prepared
 * INSERT statement.
 */
function processRecurring($pdo, $userId) {
    // Fetch all active recurring templates for this user
    $getRecurring = $pdo->prepare("
        SELECT * FROM expenses
        WHERE user_id = ?
        AND is_recurring = 1
    ");
    $getRecurring->execute([$userId]);
    $recurringRecords = $getRecurring->fetchAll();

    if (empty($recurringRecords)) {
        return;
    }

    $parentIds    = array_column($recurringRecords, 'id');
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));

    // Load every existing generated child (parent_id + date) in one query
    $existingMap = [];
    $getExisting = $pdo->prepare("
        SELECT parent_id, date FROM expenses
        WHERE user_id = ? AND parent_id IN ($placeholders)
    ");
    $getExisting->execute(array_merge([$userId], $parentIds));
    foreach ($getExisting->fetchAll() as $row) {
        $existingMap[$row['parent_id']][$row['date']] = true;
    }

    // Prepare the INSERT statement once and reuse it
    // Generated children are always dated today or earlier (cutoff = today),
    // so they are inserted already marked as paid.
    $insertEntry = $pdo->prepare("
        INSERT INTO expenses
        (user_id, category_id, amount, type, description, date, is_recurring, recurring_interval, recurring_end_date, parent_id, paid, paid_at)
        VALUES (?, ?, ?, ?, ?, ?, 0, NULL, NULL, ?, 1, ?)
    ");

    $today    = new DateTime('today');
    $stepMap  = [
        'daily'   => '+1 day',
        'weekly'  => '+1 week',
        'monthly' => '+1 month',
        'yearly'  => '+1 year',
    ];

    foreach ($recurringRecords as $record) {
        $interval = $record["recurring_interval"];
        if (empty($interval) || !isset($stepMap[$interval])) {
            continue;
        }

        $startDate  = new DateTime($record["date"]);
        $endDate    = $record["recurring_end_date"] ? new DateTime($record["recurring_end_date"]) : null;

        // Cutoff boundary is either the specified end date or today, whichever is earlier
        $cutoffDate = ($endDate && $endDate < $today) ? $endDate : $today;

        // First generated date after start date
        $nextDate = clone $startDate;
        $nextDate->modify($stepMap[$interval]);

        // Generate entries up to the cutoff date
        while ($nextDate <= $cutoffDate) {
            $formattedDate = $nextDate->format('Y-m-d');

            // Insert only if it does not already exist for this parent + date
            if (empty($existingMap[$record["id"]][$formattedDate])) {
                $insertEntry->execute([
                    $userId,
                    $record["category_id"],
                    $record["amount"],
                    $record["type"],
                    $record["description"],
                    $formattedDate,
                    $record["id"],
                    date('Y-m-d H:i:s')
                ]);
                // Track it so duplicate inserts are skipped within this run
                $existingMap[$record["id"]][$formattedDate] = true;
            }

            $nextDate->modify($stepMap[$interval]);
        }
    }
}

