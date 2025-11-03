<?php
require_once 'config.php';

// Get statistics
$db = getDB();

// Total customers
$stmt = $db->query("SELECT COUNT(*) as total FROM customers");
$total_customers = $stmt->fetch()['total'];

// Recent customers
$stmt = $db->query("SELECT COUNT(*) as total FROM customers WHERE DATE(signup_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$recent_customers = $stmt->fetch()['total'];

// Food visits today
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'food' AND DATE(visit_date) = CURDATE()");
$food_visits_today = $stmt->fetch()['total'];

// Food visits this month
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'food' AND MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
$food_visits_month = $stmt->fetch()['total'];

// Money visits today
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'money' AND DATE(visit_date) = CURDATE()");
$money_visits_today = $stmt->fetch()['total'];

// Money visits this month
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'money' AND MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
$money_visits_month = $stmt->fetch()['total'];

// Voucher visits today
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'voucher' AND DATE(visit_date) = CURDATE()");
$voucher_visits_today = $stmt->fetch()['total'];

// Voucher visits this month
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_type = 'voucher' AND MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
$voucher_visits_month = $stmt->fetch()['total'];

$page_title = "Dashboard";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1><?php echo htmlspecialchars(getSetting('organization_name', 'NexusDB')); ?> Dashboard</h1>
        <p class="lead">Food Distribution Service Management System</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="people"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($total_customers); ?></h3>
                <p>Total <?php echo htmlspecialchars(getCustomerTermPlural('Customers')); ?></p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="calendar"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($recent_customers); ?></h3>
                <p>New (Last 30 Days)</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="restaurant"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($food_visits_today); ?></h3>
                <p>Food Visits Today</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="restaurant"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($food_visits_month); ?></h3>
                <p>Food Visits This Month</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="cash"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($money_visits_today); ?></h3>
                <p>Money Visits Today</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="cash"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($money_visits_month); ?></h3>
                <p>Money Visits This Month</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="ticket"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($voucher_visits_today); ?></h3>
                <p>Voucher Visits Today</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="ticket"></ion-icon></div>
            <div class="stat-info">
                <h3><?php echo number_format($voucher_visits_month); ?></h3>
                <p>Voucher Visits This Month</p>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="signup.php" class="btn btn-primary btn-large">
            <span class="btn-icon"><ion-icon name="add"></ion-icon></span>
            <span>New <?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> Signup</span>
        </a>
        <a href="customers.php" class="btn btn-secondary btn-large">
            <span class="btn-icon"><ion-icon name="search"></ion-icon></span>
            <span>Search <?php echo htmlspecialchars(getCustomerTermPlural('Customers')); ?></span>
        </a>
        <a href="visits_food.php" class="btn btn-secondary btn-large">
            <span class="btn-icon"><ion-icon name="restaurant"></ion-icon></span>
            <span>Record Food Visit</span>
        </a>
        <a href="reports.php" class="btn btn-secondary btn-large">
            <span class="btn-icon"><ion-icon name="stats-chart"></ion-icon></span>
            <span>Reports</span>
        </a>
    </div>

    <div class="recent-section">
        <h2>Recent <?php echo htmlspecialchars(getCustomerTermPlural('Customers')); ?></h2>
        <?php
        $stmt = $db->query("SELECT c.*, 
                           (SELECT COUNT(*) FROM visits WHERE customer_id = c.id) as visit_count
                           FROM customers c 
                           ORDER BY c.created_at DESC 
                           LIMIT 10");
        $recent_customers_list = $stmt->fetchAll();
        
        if (count($recent_customers_list) > 0) {
            echo '<table class="data-table">';
            echo '<thead><tr><th>Name</th><th>Phone</th><th>Signup Date</th><th>City</th><th>Visits</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            foreach ($recent_customers_list as $customer) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($customer['name']) . '</td>';
                echo '<td>' . htmlspecialchars($customer['phone']) . '</td>';
                echo '<td>' . date('M d, Y g:i A', strtotime($customer['signup_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($customer['city']) . '</td>';
                echo '<td>' . $customer['visit_count'] . '</td>';
                echo '<td><a href="customer_view.php?id=' . $customer['id'] . '" class="btn btn-small">View</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-data">No ' . strtolower(getCustomerTermPlural('customers')) . ' yet. <a href="signup.php">Add ' . htmlspecialchars(getCustomerTerm('Customer')) . '</a></p>';
        }
        ?>
    </div>
</div>

<?php include 'footer.php'; ?>

