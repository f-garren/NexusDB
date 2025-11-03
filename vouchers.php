<?php
require_once 'config.php';

$error = '';
$success = '';
$db = getDB();

// Get filter parameters
$filter_search = $_GET['search'] ?? '';
$filter_issued_date = $_GET['issued_date'] ?? '';
$filter_redeemed_date = $_GET['redeemed_date'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($filter_search)) {
    // Search in voucher code, customer name, phone, and household member names
    $stmt = $db->prepare("SELECT DISTINCT customer_id FROM household_members WHERE name LIKE ?");
    $stmt->execute(["%$filter_search%"]);
    $household_customer_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($household_customer_ids)) {
        $household_placeholders = str_repeat('?,', count($household_customer_ids) - 1) . '?';
        $where_conditions[] = "(v.voucher_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR v.customer_id IN ($household_placeholders))";
        $search_term = "%$filter_search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params = array_merge($params, $household_customer_ids);
    } else {
        $where_conditions[] = "(v.voucher_code LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)";
        $search_term = "%$filter_search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
}

if (!empty($filter_issued_date)) {
    $where_conditions[] = "DATE(v.issued_date) = ?";
    $params[] = $filter_issued_date;
}

if (!empty($filter_redeemed_date)) {
    $where_conditions[] = "DATE(v.redeemed_date) = ?";
    $params[] = $filter_redeemed_date;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get vouchers with filters
$query = "SELECT v.*, c.name as customer_name, c.phone 
         FROM vouchers v 
         INNER JOIN customers c ON v.customer_id = c.id 
         $where_clause
         ORDER BY v.issued_date DESC 
         LIMIT 200";

if (!empty($params)) {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $vouchers = $stmt->fetchAll();
} else {
    $stmt = $db->query($query);
    $vouchers = $stmt->fetchAll();
}


$page_title = "Manage Vouchers";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Voucher Management</h1>
        <p class="lead">View and manage issued vouchers</p>
    </div>

    <div class="action-buttons">
        <a href="visits_voucher.php" class="btn btn-primary btn-large">Create New Voucher</a>
        <a href="voucher_redemption.php" class="btn btn-secondary btn-large">Redeem Voucher</a>
    </div>

    <div class="search-box">
        <form method="GET" action="" class="filter-form">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" name="search" id="search" placeholder="Voucher code, customer name, phone, or household name..." value="<?php echo htmlspecialchars($filter_search); ?>" class="search-input">
                </div>
                
                <div class="filter-group">
                    <label for="issued_date">Issued On Date</label>
                    <input type="date" name="issued_date" id="issued_date" value="<?php echo htmlspecialchars($filter_issued_date); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="redeemed_date">Redeemed On Date</label>
                    <input type="date" name="redeemed_date" id="redeemed_date" value="<?php echo htmlspecialchars($filter_redeemed_date); ?>">
                </div>
                
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <?php if (!empty($filter_search) || !empty($filter_issued_date) || !empty($filter_redeemed_date)): ?>
                            <a href="vouchers.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </form>
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

