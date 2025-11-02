<?php
require_once 'config.php';

$error = '';
$success = '';
$customer_id = $_GET['customer_id'] ?? 0;
$db = getDB();

// Get visit limits
$visits_per_month = intval(getSetting('visits_per_month_limit', 2));
$visits_per_year = intval(getSetting('visits_per_year_limit', 12));
$min_days_between = intval(getSetting('min_days_between_visits', 14));

// Get customer if ID provided
$customer = null;
if ($customer_id) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $visit_date = $_POST['visit_date'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        // Validate customer exists
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception("Customer not found");
        }
        
        // Check visit limits
        $visit_timestamp = strtotime($visit_date);
        $visit_month = date('Y-m', $visit_timestamp);
        $visit_year = date('Y', $visit_timestamp);
        
        // Count visits in the same month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND DATE_FORMAT(visit_date, '%Y-%m') = ?");
        $stmt->execute([$customer_id, $visit_month]);
        $month_visits = $stmt->fetch()['count'];
        
        if ($month_visits >= $visits_per_month) {
            throw new Exception("Monthly visit limit reached. This customer has {$month_visits} visits this month (limit: {$visits_per_month}).");
        }
        
        // Count visits in the same year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND YEAR(visit_date) = ?");
        $stmt->execute([$customer_id, $visit_year]);
        $year_visits = $stmt->fetch()['count'];
        
        if ($year_visits >= $visits_per_year) {
            throw new Exception("Yearly visit limit reached. This customer has {$year_visits} visits this year (limit: {$visits_per_year}).");
        }
        
        // Check minimum days between visits
        $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id = ? AND visit_date < ?");
        $stmt->execute([$customer_id, $visit_date]);
        $last_visit = $stmt->fetch()['last_visit'];
        
        if ($last_visit) {
            $days_since = floor((strtotime($visit_date) - strtotime($last_visit)) / 86400);
            if ($days_since < $min_days_between) {
                throw new Exception("Minimum {$min_days_between} days required between visits. Last visit was {$days_since} days ago.");
            }
        }
        
        // Insert visit
        $stmt = $db->prepare("INSERT INTO visits (customer_id, visit_date, notes) VALUES (?, ?, ?)");
        $stmt->execute([$customer_id, $visit_date, $notes]);
        
        $success = "Visit recorded successfully! <a href='customer_view.php?id=" . $customer_id . "'>View customer</a>";
        
        // Clear customer selection
        $customer = null;
        $customer_id = 0;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$page_title = "Record Visit";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Record Visit</h1>
        <p class="lead">Track customer visits with automatic limit checking</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="visit-limits-info">
        <h3>Visit Limits</h3>
        <ul>
            <li>Per Month: <?php echo $visits_per_month; ?> visits</li>
            <li>Per Year: <?php echo $visits_per_year; ?> visits</li>
            <li>Minimum Days Between Visits: <?php echo $min_days_between; ?> days</li>
        </ul>
    </div>

    <form method="POST" action="" class="visit-form">
        <div class="form-group">
            <label for="customer_search">Search Customer <span class="required">*</span></label>
            <input type="text" id="customer_search" placeholder="Type customer name or phone..." autocomplete="off">
            <input type="hidden" id="customer_id" name="customer_id" required>
            <div id="customer_results" class="search-results"></div>
        </div>
        
        <?php if ($customer): ?>
            <div class="selected-customer">
                <strong>Selected:</strong> <?php echo htmlspecialchars($customer['name']); ?> 
                (<?php echo htmlspecialchars($customer['phone']); ?>)
                
                <?php
                // Show current visit statistics
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND DATE_FORMAT(visit_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
                $stmt->execute([$customer['id']]);
                $current_month = $stmt->fetch()['count'];
                
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND YEAR(visit_date) = YEAR(CURDATE())");
                $stmt->execute([$customer['id']]);
                $current_year = $stmt->fetch()['count'];
                
                $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id = ?");
                $stmt->execute([$customer['id']]);
                $last_visit = $stmt->fetch()['last_visit'];
                $days_since = $last_visit ? floor((time() - strtotime($last_visit)) / 86400) : null;
                ?>
                
                <div class="customer-stats">
                    <p>This month: <?php echo $current_month; ?>/<?php echo $visits_per_month; ?></p>
                    <p>This year: <?php echo $current_year; ?>/<?php echo $visits_per_year; ?></p>
                    <?php if ($days_since !== null): ?>
                        <p>Last visit: <?php echo $days_since; ?> days ago</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="visit_date">Visit Date <span class="required">*</span></label>
            <input type="date" id="visit_date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required>
        </div>

        <div class="form-group">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4" placeholder="Optional notes about this visit..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-large">Record Visit</button>
            <a href="index.php" class="btn btn-secondary btn-large">Cancel</a>
        </div>
    </form>
</div>

<script>
let searchTimeout;
const customerSearch = document.getElementById('customer_search');
const customerIdInput = document.getElementById('customer_id');
const customerResults = document.getElementById('customer_results');

customerSearch.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) {
        customerResults.innerHTML = '';
        customerIdInput.value = '';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`customer_search.php?q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                customerResults.innerHTML = '';
                if (data.length === 0) {
                    customerResults.innerHTML = '<div class="no-results">No customers found</div>';
                    return;
                }
                
                data.forEach(customer => {
                    const div = document.createElement('div');
                    div.className = 'customer-result';
                    div.innerHTML = `
                        <strong>${customer.name}</strong><br>
                        <small>${customer.phone} - ${customer.city}, ${customer.state}</small>
                    `;
                    div.addEventListener('click', () => {
                        customerIdInput.value = customer.id;
                        customerSearch.value = customer.name;
                        customerResults.innerHTML = '';
                        window.location.href = `visits.php?customer_id=${customer.id}`;
                    });
                    customerResults.appendChild(div);
                });
            })
            .catch(error => {
                console.error('Search error:', error);
            });
    }, 300);
});

// Hide results when clicking outside
document.addEventListener('click', function(e) {
    if (!customerSearch.contains(e.target) && !customerResults.contains(e.target)) {
        customerResults.innerHTML = '';
    }
});
</script>

<?php include 'footer.php'; ?>

