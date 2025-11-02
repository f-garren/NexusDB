<?php
require_once 'config.php';

$db = getDB();
$page_title = "Reports";
include 'header.php';

// Get statistics
$stmt = $db->query("SELECT COUNT(*) as total FROM customers");
$total_customers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$visits_30_days = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$visits_7_days = $stmt->fetch()['total'];

// Top customers by visits
$stmt = $db->query("SELECT c.name, c.phone, COUNT(v.id) as visit_count 
                   FROM customers c 
                   LEFT JOIN visits v ON c.id = v.customer_id 
                   GROUP BY c.id 
                   ORDER BY visit_count DESC 
                   LIMIT 10");
$top_customers = $stmt->fetchAll();

// Monthly visit trends
$stmt = $db->query("SELECT DATE_FORMAT(visit_date, '%Y-%m') as month, COUNT(*) as count 
                   FROM visits 
                   WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                   GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
                   ORDER BY month");
$monthly_trends = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Reports & Statistics</h1>
        <p class="lead">View insights and analytics</p>
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
                <h3><?php echo number_format($visits_30_days); ?></h3>
                <p>Visits (Last 30 Days)</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-info">
                <h3><?php echo number_format($visits_7_days); ?></h3>
                <p>Visits (Last 7 Days)</p>
            </div>
        </div>
    </div>

    <div class="report-section">
        <h2>Top Customers by Visit Count</h2>
        <?php if (count($top_customers) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Total Visits</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_customers as $index => $customer): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo $customer['visit_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No visit data available.</p>
        <?php endif; ?>
    </div>

    <div class="report-section">
        <h2>Monthly Visit Trends (Last 12 Months)</h2>
        <?php if (count($monthly_trends) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Visit Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_trends as $trend): ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($trend['month'] . '-01')); ?></td>
                            <td><?php echo $trend['count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No visit data available.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>

