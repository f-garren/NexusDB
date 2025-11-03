<?php
require_once 'config.php';

$error = '';
$success = '';
$show_confirmation = false;
$form_data = [];
$customer_id = $_GET['customer_id'] ?? 0;
$db = getDB();

// Get customer if ID provided
$customer = null;
if ($customer_id) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
}

// Handle confirmation submission (final save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_submit'])) {
    $p = $_POST;
    $customer_id = intval($p['customer_id']);
    $visit_type = 'voucher'; // Hardcoded for voucher visits
    
    try {
        // Validate customer exists
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception("Customer not found");
        }
        
        // Use current timestamp for visit (no override allowed)
        $visit_timestamp = time();
        $visit_date = date('Y-m-d H:i:s');
        
        // Check voucher limits
        $voucher_limit_month = intval(getSetting('voucher_limit_month', -1));
        $voucher_limit_year = intval(getSetting('voucher_limit_year', -1));
        $voucher_min_days_between = intval(getSetting('voucher_min_days_between', -1));
        
        $visit_month = date('Y-m', $visit_timestamp);
        $visit_year = date('Y', $visit_timestamp);
        
        // Count voucher visits this month
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'voucher' AND DATE_FORMAT(visit_date, '%Y-%m') = ?");
        $stmt->execute([$customer_id, $visit_month]);
        $voucher_visits_month = $stmt->fetch()['count'];
        
        // Check monthly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($voucher_limit_month >= 0 && $voucher_visits_month >= $voucher_limit_month) {
            $limit_text = $voucher_limit_month == 0 ? 'disabled' : $voucher_limit_month;
            throw new Exception("Monthly voucher limit reached. This customer has {$voucher_visits_month} voucher visits this month (limit: {$limit_text}).");
        }
        
        // Count voucher visits this year
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id = ? AND visit_type = 'voucher' AND YEAR(visit_date) = ?");
        $stmt->execute([$customer_id, $visit_year]);
        $voucher_visits_year = $stmt->fetch()['count'];
        
        // Check yearly limit (-1 = unlimited, 0 = disabled, >0 = limit)
        if ($voucher_limit_year >= 0 && $voucher_visits_year >= $voucher_limit_year) {
            $limit_text = $voucher_limit_year == 0 ? 'disabled' : $voucher_limit_year;
            throw new Exception("Yearly voucher limit reached. This customer has {$voucher_visits_year} voucher visits this year (limit: {$limit_text}).");
        }
        
        // Check minimum days between voucher visits (-1 = unlimited, 0 = disabled, >0 = minimum days)
        if ($voucher_min_days_between > 0) {
            $stmt = $db->prepare("SELECT MAX(visit_date) as last_visit FROM visits WHERE customer_id = ? AND visit_type = 'voucher' AND visit_date < ?");
            $stmt->execute([$customer_id, $visit_date]);
            $last_visit = $stmt->fetch()['last_visit'];
            
            if ($last_visit) {
                $days_since = floor(($visit_timestamp - strtotime($last_visit)) / 86400);
                if ($days_since < $voucher_min_days_between) {
                    throw new Exception("Minimum {$voucher_min_days_between} days required between voucher visits. Last voucher visit was {$days_since} days ago.");
                }
            }
        }
        
        // Voucher creation - create voucher record
        if (empty($p['voucher_amount'])) {
            throw new Exception("Voucher amount is required");
        }
        $voucher_amount = floatval($p['voucher_amount']);
        if ($voucher_amount <= 0) {
            throw new Exception("Voucher amount must be greater than 0");
        }
        
        // Generate unique voucher code
        $voucher_prefix = getSetting('voucher_prefix', 'VCH-');
        $voucher_code = $voucher_prefix . strtoupper(substr(md5(time() . $customer_id . rand()), 0, 8));
        
        // Check if code exists (unlikely but possible)
        $stmt = $db->prepare("SELECT id FROM vouchers WHERE voucher_code = ?");
        $stmt->execute([$voucher_code]);
        while ($stmt->fetch()) {
            $voucher_code = $voucher_prefix . strtoupper(substr(md5(time() . $customer_id . rand()), 0, 8));
            $stmt->execute([$voucher_code]);
        }
        
        // Insert visit
        $notes = $p['notes'] ?? '';
        $stmt = $db->prepare("INSERT INTO visits (customer_id, visit_date, visit_type, notes) VALUES (?, ?, 'voucher', ?)");
        $stmt->execute([$customer_id, $visit_date, $notes]);
        
        // Create voucher record
        $stmt = $db->prepare("INSERT INTO vouchers (voucher_code, customer_id, amount, issued_date, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$voucher_code, $customer_id, $voucher_amount, $visit_date, $notes]);
        
        $success = "Voucher created successfully! Voucher Code: <strong>{$voucher_code}</strong> - Amount: $" . number_format($voucher_amount, 2) . " <a href='customer_view.php?id=" . $customer_id . "'>View customer</a>";
        
        // Clear customer selection
        $customer = null;
        $customer_id = 0;
        $show_confirmation = false;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle initial form submission - show confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm_submit'])) {
    $form_data = $_POST;
    $customer_id = intval($_POST['customer_id']);
    
    // Get customer for confirmation screen
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        $show_confirmation = true;
    }
}

$page_title = "Create Voucher";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Create Voucher</h1>
        <p class="lead">Create a new voucher for a customer</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($show_confirmation && $customer): ?>
        <!-- Confirmation Screen -->
        <div class="confirmation-screen">
            <h2>Confirm Visit</h2>
            <p class="lead">Please review the visit information before submitting.</p>
            
            <div class="confirmation-summary">
                <h3>Visit Summary</h3>
                <table class="info-table">
                    <tr><th>Customer:</th><td><?php echo htmlspecialchars($customer['name']); ?></td></tr>
                    <tr><th>Phone:</th><td><?php echo htmlspecialchars($customer['phone']); ?></td></tr>
                    <tr><th>Visit Type:</th><td><strong>Voucher</strong></td></tr>
                    <tr><th>Visit Date & Time:</th><td><?php echo date('F d, Y \a\t g:i A'); ?></td></tr>
                    <?php if (!empty($form_data['voucher_amount'])): ?>
                    <tr><th>Voucher Amount:</th><td>$<?php echo number_format(floatval($form_data['voucher_amount']), 2); ?></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($form_data['notes'])): ?>
                    <tr><th>Notes:</th><td><?php echo nl2br(htmlspecialchars($form_data['notes'])); ?></td></tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <!-- Hidden form to preserve data -->
            <form method="POST" action="" style="display: none;" id="confirm_form">
                <?php foreach ($form_data as $key => $value): ?>
                    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
                <?php endforeach; ?>
                <input type="hidden" name="confirm_submit" value="1">
            </form>
            
            <div class="form-actions">
                <button type="button" onclick="document.getElementById('confirm_form').submit();" class="btn btn-primary btn-large">Confirm and Submit</button>
                <button type="button" onclick="window.location.href='visits_voucher.php';" class="btn btn-secondary btn-large">Cancel / Edit</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Voucher Form -->
        <form method="POST" action="" class="visit-form">
            <div class="form-group" style="position: relative;">
                <label for="customer_search">Search <?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> <span class="required">*</span></label>
                <input type="text" id="customer_search" placeholder="Type <?php echo strtolower(getCustomerTerm('customer')); ?> name or phone..." autocomplete="off" value="<?php echo $customer ? htmlspecialchars($customer['name']) : ''; ?>">
                <input type="hidden" id="customer_id" name="customer_id" value="<?php echo $customer ? $customer['id'] : ''; ?>" required>
                <div id="customer_results" class="search-results"></div>
            </div>
            
            <?php if ($customer): ?>
                <div class="selected-customer" id="customer_info">
                    <strong>Selected:</strong> <?php echo htmlspecialchars($customer['name']); ?> 
                    (<?php echo htmlspecialchars($customer['phone']); ?>)
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="voucher_amount">Voucher Amount ($) <span class="required">*</span></label>
                <input type="number" id="voucher_amount" name="voucher_amount" step="0.01" min="0.01" placeholder="0.00" required>
                <small class="help-text">Enter the voucher dollar amount</small>
            </div>

            <input type="hidden" name="visit_date" value="<?php echo date('Y-m-d H:i:s'); ?>">

            <div class="form-group">
                <label for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="4" placeholder="Optional notes about this visit..."></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-large">Review & Submit</button>
                <a href="index.php" class="btn btn-secondary btn-large">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
let searchTimeout;
const customerSearch = document.getElementById('customer_search');
const customerIdInput = document.getElementById('customer_id');

if (customerSearch) {
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
                        customerResults.innerHTML = '<div class="no-results">No <?php echo strtolower(getCustomerTermPlural('customers')); ?> found</div>';
                        return;
                    }
                    
                    data.forEach(customer => {
                        const div = document.createElement('div');
                        div.className = 'customer-result';
                        div.innerHTML = `
                            <strong>${customer.name}</strong><br>
                            <small>${customer.phone} - ${customer.city || ''}, ${customer.state || ''}</small>
                        `;
                        div.addEventListener('click', () => {
                            customerIdInput.value = customer.id;
                            customerSearch.value = customer.name;
                            customerResults.innerHTML = '';
                            window.location.href = `visits_voucher.php?customer_id=${customer.id}`;
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
}
</script>

<?php include 'footer.php'; ?>
