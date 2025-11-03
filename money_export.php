<?php
require_once 'config.php';

$db = getDB();

// Get all money visits
$stmt = $db->query("SELECT v.*, c.name as customer_name, c.phone, c.address, c.city, c.state 
                   FROM visits v 
                   INNER JOIN customers c ON v.customer_id = c.id 
                   WHERE v.visit_type = 'money' 
                   ORDER BY v.visit_date DESC");
$money_visits = $stmt->fetchAll();

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="money_visits_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, ['Date', 'Customer Name', 'Phone', 'Address', 'City', 'State', 'Notes']);

// Write data rows
foreach ($money_visits as $visit) {
    fputcsv($output, [
        date('Y-m-d H:i:s', strtotime($visit['visit_date'])),
        $visit['customer_name'],
        $visit['phone'],
        $visit['address'],
        $visit['city'],
        $visit['state'],
        $visit['notes'] ?? ''
    ]);
}

fclose($output);
exit;
?>

