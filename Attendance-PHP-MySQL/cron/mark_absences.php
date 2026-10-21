<?php
// cron/mark_absences.php — Auto-mark absent for employees with a scheduled shift who didn't clock in

require_once __DIR__ . '/../includes/config.php';

// Which date to process (default today)
$target_date = $argv[1] ?? date('Y-m-d');

// Validate date
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $target_date)) {
    fwrite(STDERR, "Invalid date format. Use YYYY-MM-DD.\n");
    exit(1);
}

// Day of week (0=Sunday, 1=Monday, ..., 6=Saturday)
$day_of_week = (int)date('w', strtotime($target_date));

echo "=== Mark Absences for $target_date (day: $day_of_week) ===\n";

// Check if target_date is a holiday
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM holidays WHERE date = ?");
    $stmt->execute([$target_date]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "Date is a holiday. Skipping.\n";
        exit(0);
    }
} catch (PDOException $e) {}

// Find all active employees with a shift that works this day
$employees_on_shift = [];
try {
    $stmt = $pdo->prepare("
        SELECT e.id, e.first_name, e.last_name, e.employee_code,
               s.id AS shift_id, s.name AS shift_name, s.days AS shift_days
        FROM employees e
        JOIN shifts s ON s.id = e.shift_id
        WHERE e.is_active = 1 AND s.is_active = 1
    ");
    $stmt->execute();
    $all = $stmt->fetchAll();

    foreach ($all as $emp) {
        $shift_days = explode(',', $emp['shift_days']);
        if (in_array((string)$day_of_week, $shift_days)) {
            $employees_on_shift[] = $emp;
        }
    }
} catch (PDOException $e) {
    fwrite(STDERR, "Query failed: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Found " . count($employees_on_shift) . " employees scheduled for this day.\n";

$marked = 0;
$skipped = 0;

foreach ($employees_on_shift as $emp) {
    // Check if there's already an attendance record
    try {
        $stmt = $pdo->prepare("SELECT id, status FROM attendance WHERE employee_id = ? AND date = ? LIMIT 1");
        $stmt->execute([$emp['id'], $target_date]);
        $existing = $stmt->fetch();

        if ($existing) {
            echo "  ✓ {$emp['first_name']} {$emp['last_name']} — already recorded ({$existing['status']})\n";
            $skipped++;
            continue;
        }

        // Mark absent
        $pdo->prepare("
            INSERT INTO attendance (employee_id, date, status, notes)
            VALUES (?, ?, 'absent', 'Auto-marked as absent — no clock-in')
        ")->execute([$emp['id'], $target_date]);

        echo "  ✗ {$emp['first_name']} {$emp['last_name']} — MARKED ABSENT\n";
        $marked++;
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed for {$emp['first_name']}: " . $e->getMessage() . "\n");
    }
}

echo "\n=== Summary ===\n";
echo "Marked absent: $marked\n";
echo "Already recorded (skipped): $skipped\n";
echo "Done.\n";
