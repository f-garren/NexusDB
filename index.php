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

// Total visits today
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_date = CURDATE()");
$visits_today = $stmt->fetch()['total'];

// Total visits this month
$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
$visits_month = $stmt->fetch()['total'];

$page_title = "Dashboard";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>NexusDB Dashboard</h1>
        <p class="lead">Food Distribution Service Management System</p>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">👥</div>
            <div class="stat-info">
                <h3><?php echo number_format($total_customers); ?></h3>
                <p>Total Customers</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📅</div>
            <div class="stat-info">
                <h3><?php echo number_format($recent_customers); ?></h3>
                <p>New (Last 30 Days)</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">✓</div>
            <div class="stat-info">
                <h3><?php echo number_format($visits_today); ?></h3>
                <p>Visits Today</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3><?php echo number_format($visits_month); ?></h3>
                <p>Visits This Month</p>
            </div>
        </div>
    </div>

    <div class="action-buttons">
        <a href="signup.php" class="btn btn-primary btn-large">
            <span class="btn-icon">➕</span>
            <span>New Customer Signup</span>
        </a>
        <a href="customers.php" class="btn btn-secondary btn-large">
            <span class="btn-icon">🔍</span>
            <span>Search Customers</span>
        </a>
        <a href="visits.php" class="btn btn-secondary btn-large">
            <span class="btn-icon">📋</span>
            <span>Record Visit</span>
        </a>
        <a href="reports.php" class="btn btn-secondary btn-large">
            <span class="btn-icon">📈</span>
            <span>Reports</span>
        </a>
    </div>

    <div class="recent-section">
        <h2>Recent Customers</h2>
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
                echo '<td>' . date('M d, Y', strtotime($customer['signup_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($customer['city']) . '</td>';
                echo '<td>' . $customer['visit_count'] . '</td>';
                echo '<td><a href="customer_view.php?id=' . $customer['id'] . '" class="btn btn-small">View</a></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p class="no-data">No customers yet. <a href="signup.php">Add your first customer</a></p>';
        }
        ?>
    </div>
</div>

<?php include 'footer.php'; ?>

