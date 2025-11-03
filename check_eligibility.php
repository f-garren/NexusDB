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
        
        // Check monthly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($visits_per_month >= 0 && $month_visits >= $visits_per_month) {
            $limit_text = $visits_per_month == 0 ? 'disabled' : $visits_per_month;
            $response['eligible'] = false;
            $response['errors'][] = "Monthly food visit limit reached ({$month_visits}/{$limit_text})";
        }
        
        // Count food visits in the same year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'food' AND YEAR(visit_date) = ?");
        $stmt->execute([$customer_id, $visit_year]);
        $year_visits = $stmt->fetch()['count'];
        
        // Check yearly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($visits_per_year >= 0 && $year_visits >= $visits_per_year) {
            $limit_text = $visits_per_year == 0 ? 'disabled' : $visits_per_year;
            $response['eligible'] = false;
            $response['errors'][] = "Yearly food visit limit reached ({$year_visits}/{$limit_text})";
        }
        
        // Check minimum days between food visits (-1 = unlimited, 0 = disabled, >0 = minimum days)
        if ($min_days_between > 0) {
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
        }
    } elseif ($visit_type === 'money') {
        $money_limit = intval(getSetting('money_distribution_limit', 3));
        $money_limit_month = intval(getSetting('money_distribution_limit_month', -1));
        $money_limit_year = intval(getSetting('money_distribution_limit_year', -1));
        $money_min_days_between = intval(getSetting('money_min_days_between', -1));
        
        $visit_timestamp = time();
        $visit_date = date('Y-m-d H:i:s');
        $visit_month = date('Y-m', $visit_timestamp);
        $visit_year = date('Y', $visit_timestamp);
        
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
        
        // Count money visits for all household members (total)
        $placeholders = str_repeat('?,', count($household_customer_ids) - 1) . '?';
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money'");
        $stmt->execute($household_customer_ids);
        $money_visits_count = $stmt->fetch()['count'];
        
        // Check total household limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($money_limit >= 0 && $money_visits_count >= $money_limit) {
            $limit_text = $money_limit == 0 ? 'disabled' : $money_limit;
            $response['eligible'] = false;
            $response['errors'][] = "Money assistance limit reached ({$money_visits_count}/{$limit_text})";
        }
        
        // Count money visits this month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money' AND DATE_FORMAT(visit_date, '%Y-%m') = ?");
        $stmt->execute(array_merge($household_customer_ids, [$visit_month]));
        $money_visits_month = $stmt->fetch()['count'];
        
        // Check monthly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($money_limit_month >= 0 && $money_visits_month >= $money_limit_month) {
            $limit_text = $money_limit_month == 0 ? 'disabled' : $money_limit_month;
            $response['eligible'] = false;
            $response['errors'][] = "Monthly money assistance limit reached ({$money_visits_month}/{$limit_text})";
        }
        
        // Count money visits this year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money' AND YEAR(visit_date) = ?");
        $stmt->execute(array_merge($household_customer_ids, [$visit_year]));
        $money_visits_year = $stmt->fetch()['count'];
        
        // Check yearly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($money_limit_year >= 0 && $money_visits_year >= $money_limit_year) {
            $limit_text = $money_limit_year == 0 ? 'disabled' : $money_limit_year;
            $response['eligible'] = false;
            $response['errors'][] = "Yearly money assistance limit reached ({$money_visits_year}/{$limit_text})";
        }
        
        // Check minimum days between money visits (-1 = unlimited, 0 = disabled, >0 = minimum days)
        if ($money_min_days_between > 0) {
            $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money'");
            $stmt->execute($household_customer_ids);
            $last_visit = $stmt->fetch()['last_visit'];
            
            if ($last_visit) {
                $days_since = floor((time() - strtotime($last_visit)) / 86400);
                if ($days_since < $money_min_days_between) {
                    $response['eligible'] = false;
                    $response['errors'][] = "Minimum {$money_min_days_between} days required between money visits (last visit was {$days_since} days ago)";
                }
            }
        }
    } elseif ($visit_type === 'voucher') {
        $voucher_limit_month = intval(getSetting('voucher_limit_month', -1));
        $voucher_limit_year = intval(getSetting('voucher_limit_year', -1));
        $voucher_min_days_between = intval(getSetting('voucher_min_days_between', -1));
        
        $visit_timestamp = time();
        $visit_date = date('Y-m-d H:i:s');
        $visit_month = date('Y-m', $visit_timestamp);
        $visit_year = date('Y', $visit_timestamp);
        
        // Count voucher visits this month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'voucher' AND DATE_FORMAT(visit_date, '%Y-%m') = ?");
        $stmt->execute([$customer_id, $visit_month]);
        $voucher_visits_month = $stmt->fetch()['count'];
        
        // Check monthly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($voucher_limit_month >= 0 && $voucher_visits_month >= $voucher_limit_month) {
            $limit_text = $voucher_limit_month == 0 ? 'disabled' : $voucher_limit_month;
            $response['eligible'] = false;
            $response['errors'][] = "Monthly voucher limit reached ({$voucher_visits_month}/{$limit_text})";
        }
        
        // Count voucher visits this year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'voucher' AND YEAR(visit_date) = ?");
        $stmt->execute([$customer_id, $visit_year]);
        $voucher_visits_year = $stmt->fetch()['count'];
        
        // Check yearly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($voucher_limit_year >= 0 && $voucher_visits_year >= $voucher_limit_year) {
            $limit_text = $voucher_limit_year == 0 ? 'disabled' : $voucher_limit_year;
            $response['eligible'] = false;
            $response['errors'][] = "Yearly voucher limit reached ({$voucher_visits_year}/{$limit_text})";
        }
        
        // Check minimum days between voucher visits (-1 = unlimited, 0 = disabled, >0 = minimum days)
        if ($voucher_min_days_between > 0) {
            $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id = ? AND visit_type = 'voucher'");
            $stmt->execute([$customer_id]);
            $last_visit = $stmt->fetch()['last_visit'];
            
            if ($last_visit) {
                $days_since = floor((time() - strtotime($last_visit)) / 86400);
                if ($days_since < $voucher_min_days_between) {
                    $response['eligible'] = false;
                    $response['errors'][] = "Minimum {$voucher_min_days_between} days required between voucher visits (last visit was {$days_since} days ago)";
                }
            }
        }
    }
} catch (Exception $e) {
    $response['eligible'] = false;
    $response['errors'][] = 'Error checking eligibility: ' . $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);
?>

