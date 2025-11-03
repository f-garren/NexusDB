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

// Voucher statistics
$stmt = $db->query("SELECT COUNT(*) as total FROM vouchers");
$total_vouchers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM vouchers WHERE status = 'active'");
$active_vouchers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM vouchers WHERE status = 'redeemed'");
$redeemed_vouchers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT SUM(amount) as total FROM vouchers WHERE status = 'redeemed'");
$total_redeemed_amount = $stmt->fetch()['total'] ?? 0;

// Recent vouchers
$stmt = $db->query("SELECT v.*, c.name as customer_name, c.phone 
                   FROM vouchers v 
                   INNER JOIN customers c ON v.customer_id = c.id 
                   ORDER BY v.issued_date DESC 
                   LIMIT 20");
$recent_vouchers = $stmt->fetchAll();
?>

<div class="container">
    <div class="page-header">
        <h1>Reports & Statistics</h1>
        <p class="lead">View insights and analytics</p>
        <div style="margin-top: 1rem;">
            <a href="money_export.php" class="btn btn-primary">Export Money Visits to CSV</a>
        </div>
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

    <div class="report-section">
        <h2>Voucher Statistics</h2>
        <div class="stats-grid" style="margin-bottom: 2rem;">
            <div class="stat-card">
                <div class="stat-icon">🎫</div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_vouchers); ?></h3>
                    <p>Total Vouchers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">✓</div>
                <div class="stat-info">
                    <h3><?php echo number_format($active_vouchers); ?></h3>
                    <p>Active Vouchers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3><?php echo number_format($redeemed_vouchers); ?></h3>
                    <p>Redeemed Vouchers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💵</div>
                <div class="stat-info">
                    <h3>$<?php echo number_format($total_redeemed_amount, 2); ?></h3>
                    <p>Total Redeemed Value</p>
                </div>
            </div>
        </div>

        <h3>Recent Vouchers</h3>
        <?php if (count($recent_vouchers) > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Voucher Code</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Issued Date</th>
                        <th>Status</th>
                        <th>Redeemed Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_vouchers as $voucher): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($voucher['voucher_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($voucher['customer_name']); ?></td>
                            <td>$<?php echo number_format($voucher['amount'], 2); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($voucher['issued_date'])); ?></td>
                            <td>
                                <?php if ($voucher['status'] === 'active'): ?>
                                    <span style="color: green;">Active</span>
                                <?php elseif ($voucher['status'] === 'redeemed'): ?>
                                    <span style="color: blue;">Redeemed</span>
                                <?php else: ?>
                                    <span style="color: red;">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $voucher['redeemed_date'] ? date('M d, Y g:i A', strtotime($voucher['redeemed_date'])) : '-'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No vouchers have been issued yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>

