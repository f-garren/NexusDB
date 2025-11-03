<?php
require_once 'config.php';

$customer_id = intval($_GET['customer_id'] ?? 0);
$visit_type = $_GET['visit_type'] ?? 'food';
$db = getDB();

$response = ['eligible' => true, 'errors' => []];

if ($customer_id <= 0) {
    echo json_encode(['eligible' => false, 'errors' => ['Invalid customer ID']]);
    exit;
}

try {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if (!$customer) {
        $response['eligible'] = false;
        $response['errors'][] = 'Customer not found';
        echo json_encode($response);
        exit;
    }
    
    if ($visit_type === 'food') {
        $visits_per_month = intval(getSetting('visits_per_month_limit', 2));
        $visits_per_year = intval(getSetting('visits_per_year_limit', 12));
        $min_days_between = intval(getSetting('min_days_between_visits', 14));
        
        $visit_timestamp = time();
        $visit_date = date('Y-m-d H:i:s');
        $visit_month = date('Y-m', $visit_timestamp);
        $visit_year = date('Y', $visit_timestamp);
        
        // Count food visits in the same month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'food' AND DATE_FORMAT(visit_date, '%Y-%m') = ?");
        $stmt->execute([$customer_id, $visit_month]);
        $month_visits = $stmt->fetch()['count'];
        
        if ($month_visits >= $visits_per_month) {
            $response['eligible'] = false;
            $response['errors'][] = "Monthly food visit limit reached ({$month_visits}/{$visits_per_month})";
        }
        
        // Count food visits in the same year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'food' AND YEAR(visit_date) = ?");
        $stmt->execute([$customer_id, $visit_year]);
        $year_visits = $stmt->fetch()['count'];
        
        if ($year_visits >= $visits_per_year) {
            $response['eligible'] = false;
            $response['errors'][] = "Yearly food visit limit reached ({$year_visits}/{$visits_per_year})";
        }
        
        // Check minimum days between food visits
        $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id = ? AND visit_type = 'food'");
        $stmt->execute([$customer_id]);
        $last_visit = $stmt->fetch()['last_visit'];
        
        if ($last_visit) {
            $days_since = floor((time() - strtotime($last_visit)) / 86400);
            if ($days_since < $min_days_between) {
                $response['eligible'] = false;
                $response['errors'][] = "Minimum {$min_days_between} days required between visits (last visit was {$days_since} days ago)";
            }
        }
    } elseif ($visit_type === 'money') {
        $money_limit = intval(getSetting('money_distribution_limit', 3));
        
        // Get household member names for this customer
        $stmt = $db->prepare("SELECT name FROM household_members WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $household_names = array_column($stmt->fetchAll(), 'name');
        
        // Find all customers in the same household
        $household_customer_ids = [$customer_id];
        
        if (!empty($household_names)) {
            $placeholders = str_repeat('?,', count($household_names) - 1) . '?';
            $stmt = $db->prepare("SELECT DISTINCT customer_id FROM household_members WHERE name IN ($placeholders)");
            $stmt->execute($household_names);
            $related_customers = array_column($stmt->fetchAll(), 'customer_id');
            $household_customer_ids = array_unique(array_merge($household_customer_ids, $related_customers));
        }
        
        // Count money visits for all household members
        $placeholders = str_repeat('?,', count($household_customer_ids) - 1) . '?';
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money'");
        $stmt->execute($household_customer_ids);
        $money_visits_count = $stmt->fetch()['count'];
        
        if ($money_visits_count >= $money_limit) {
            $response['eligible'] = false;
            $response['errors'][] = "Money assistance limit reached ({$money_visits_count}/{$money_limit})";
        }
    }
} catch (Exception $e) {
    $response['eligible'] = false;
    $response['errors'][] = 'Error checking eligibility: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>

