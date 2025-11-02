<?php
require_once 'config.php';

$db = getDB();
$search = $_GET['search'] ?? '';
$customers = [];

if (!empty($search)) {
    $stmt = $db->prepare("SELECT c.*, 
                         (SELECT COUNT(*) FROM visits WHERE customer_id = c.id) as visit_count
                         FROM customers c 
                         WHERE c.name LIKE ? OR c.phone LIKE ? OR c.address LIKE ?
                         ORDER BY c.name");
    $search_term = "%$search%";
    $stmt->execute([$search_term, $search_term, $search_term]);
    $customers = $stmt->fetchAll();
} else {
    $stmt = $db->query("SELECT c.*, 
                       (SELECT COUNT(*) FROM visits WHERE customer_id = c.id) as visit_count
                       FROM customers c 
                       ORDER BY c.name 
                       LIMIT 100");
    $customers = $stmt->fetchAll();
}

$page_title = "Customers";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Customers</h1>
        <p class="lead">Search and manage customer records</p>
    </div>

    <div class="search-box">
        <form method="GET" action="">
            <input type="text" name="search" placeholder="Search by name, phone, or address..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
            <button type="submit" class="btn btn-primary">Search</button>
            <?php if (!empty($search)): ?>
                <a href="customers.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (count($customers) > 0): ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>City, State</th>
                        <th>Signup Date</th>
                        <th>Visits</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($customer['name']); ?></td>
                            <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                            <td><?php echo htmlspecialchars($customer['address']); ?></td>
                            <td><?php echo htmlspecialchars($customer['city'] . ', ' . $customer['state']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($customer['signup_date'])); ?></td>
                            <td><?php echo $customer['visit_count']; ?></td>
                            <td>
                                <a href="customer_view.php?id=<?php echo $customer['id']; ?>" class="btn btn-small">View</a>
                                <a href="visits.php?customer_id=<?php echo $customer['id']; ?>" class="btn btn-small btn-primary">Record Visit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="no-data">
            <p>No customers found<?php echo !empty($search) ? ' matching your search' : ''; ?>.</p>
            <?php if (empty($search)): ?>
                <a href="signup.php" class="btn btn-primary">Add First Customer</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>

