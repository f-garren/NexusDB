<?php
require_once 'config.php';

$error = '';
$success = '';
$db = getDB();

// Get all vouchers
$stmt = $db->query("SELECT v.*, c.name as customer_name, c.phone 
                   FROM vouchers v 
                   INNER JOIN customers c ON v.customer_id = c.id 
                   ORDER BY v.issued_date DESC 
                   LIMIT 100");
$vouchers = $stmt->fetchAll();

$page_title = "Manage Vouchers";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Voucher Management</h1>
        <p class="lead">View and manage issued vouchers</p>
    </div>

    <div class="action-buttons">
        <a href="visits.php" class="btn btn-primary btn-large">Create New Voucher</a>
        <a href="voucher_redemption.php" class="btn btn-secondary btn-large">Redeem Voucher</a>
    </div>

    <?php if (count($vouchers) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Voucher Code</th>
                    <th>Customer</th>
                    <th>Amount</th>
                    <th>Issued Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vouchers as $voucher): ?>
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
                                <br><small><?php echo $voucher['redeemed_date'] ? date('M d, Y', strtotime($voucher['redeemed_date'])) : ''; ?></small>
                            <?php else: ?>
                                <span style="color: red;">Expired</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="customer_view.php?id=<?php echo $voucher['customer_id']; ?>" class="btn btn-small">View Customer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <p>No vouchers have been issued yet.</p>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

