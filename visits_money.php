<?php
require_once 'config.php';

$error = '';
$success = '';
$show_confirmation = false;
$form_data = [];
$customer_id = $_GET['customer_id'] ?? 0;
$db = getDB();

// Get money limit
$money_limit = intval(getSetting('money_distribution_limit', 3));

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
    $visit_type = 'money'; // Hardcoded for money visits
    
    try {
        // Validate customer exists
        $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
        
        if (!$customer) {
            throw new Exception("Customer not found");
        }
        
        // Use current timestamp or manual override for visit
        if (!empty($p['override_visit_date']) && !empty($p['manual_visit_datetime'])) {
            $visit_date = date('Y-m-d H:i:s', strtotime($p['manual_visit_datetime']));
            $visit_timestamp = strtotime($p['manual_visit_datetime']);
        } else {
            $visit_timestamp = time();
            $visit_date = date('Y-m-d H:i:s');
        }
        
        // Money visits: check household limit
        // Get household member names for this customer
        $stmt = $db->prepare("SELECT name FROM household_members WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        $household_names = array_column($stmt->fetchAll(), 'name');
        
        // Find all customers in the same household (share at least one household member name)
        $household_customer_ids = [$customer_id]; // Include the current customer
        
        if (!empty($household_names)) {
            $placeholders = str_repeat('?,', count($household_names) - 1) . '?';
            $stmt = $db->prepare("SELECT DISTINCT customer_id FROM household_members WHERE name IN ($placeholders)");
            $stmt->execute($household_names);
            $related_customers = array_column($stmt->fetchAll(), 'customer_id');
            $household_customer_ids = array_unique(array_merge($household_customer_ids, $related_customers));
        }
        
        // Count money visits for all household members
        $placeholders = str_repeat('?,', count($household_customer_ids) - 1) . '?';
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits WHERE customer_id IN ($placeholders) AND visit_type = 'money'");
        $stmt->execute($household_customer_ids);
        $money_visits_count = $stmt->fetch()['count'];
        
        if ($money_visits_count >= $money_limit) {
            throw new Exception("Money assistance limit reached. This household has received money assistance {$money_visits_count} times (limit: {$money_limit} times total).");
        }
        
        // Validate and get amount
        if (empty($p['amount'])) {
            throw new Exception("Amount is required for money visits");
        }
        $amount = floatval($p['amount']);
        if ($amount <= 0) {
            throw new Exception("Amount must be greater than 0");
        }
        
        // Insert visit
        $notes = $p['notes'] ?? '';
        $stmt = $db->prepare("INSERT INTO visits (customer_id, visit_date, visit_type, amount, notes) VALUES (?, ?, 'money', ?, ?)");
        $stmt->execute([$customer_id, $visit_date, $amount, $notes]);
        
        $success = "Money visit recorded successfully! Amount: $" . number_format($amount, 2) . " <a href='customer_view.php?id=" . $customer_id . "'>View customer</a>";
        
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

$page_title = "Record Money Visit";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Record Money Visit</h1>
        <p class="lead">Track money assistance with automatic limit checking</p>
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
                    <tr><th>Visit Type:</th><td><strong>Money</strong></td></tr>
                    <?php if (!empty($form_data['amount'])): ?>
                    <tr><th>Amount:</th><td>$<?php echo number_format(floatval($form_data['amount']), 2); ?></td></tr>
                    <?php endif; ?>
                    <tr><th>Visit Date & Time:</th><td><?php 
                        if (!empty($form_data['override_visit_date']) && !empty($form_data['manual_visit_datetime'])) {
                            echo date('F d, Y \a\t g:i A', strtotime($form_data['manual_visit_datetime']));
                        } else {
                            echo date('F d, Y \a\t g:i A');
                        }
                    ?></td></tr>
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
                <button type="button" onclick="window.location.href='visits_money.php';" class="btn btn-secondary btn-large">Cancel / Edit</button>
            </div>
        </div>
    <?php else: ?>
        <!-- Visit Form -->
        <div class="visit-limits-info">
            <h3>Money Visit Limits</h3>
            <ul>
                <li>Maximum <?php echo $money_limit; ?> times per household (all time)</li>
            </ul>
        </div>

        <form method="POST" action="" class="visit-form">
            <div class="form-group">
                <label for="customer_search">Search <?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> <span class="required">*</span></label>
                <input type="text" id="customer_search" placeholder="Type <?php echo strtolower(getCustomerTerm('customer')); ?> name or phone..." autocomplete="off" value="<?php echo $customer ? htmlspecialchars($customer['name']) : ''; ?>">
                <input type="hidden" id="customer_id" name="customer_id" value="<?php echo $customer ? $customer['id'] : ''; ?>" required>
            </div>
            
            <?php if ($customer): ?>
                <div class="selected-customer" id="customer_info">
                    <strong>Selected:</strong> <?php echo htmlspecialchars($customer['name']); ?> 
                    (<?php echo htmlspecialchars($customer['phone']); ?>)
                    <div id="eligibility_errors" style="margin-top: 0.5rem;"></div>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="amount">Amount ($) <span class="required">*</span></label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                <small class="help-text">Enter the money distribution amount</small>
            </div>

            <div class="form-group">
                <label for="visit_date">Visit Date & Time</label>
                <div class="checkbox-group">
                    <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                        <input type="checkbox" id="override_visit_date" name="override_visit_date" value="1">
                        <span>Override automatic date/time</span>
                    </label>
                </div>
                <div id="auto_visit_datetime">
                    <input type="text" value="<?php echo date('F d, Y \a\t g:i A'); ?>" readonly class="readonly-field">
                    <small class="help-text">Automatically recorded from system time</small>
                </div>
                <div id="manual_visit_datetime" style="display: none;">
                    <input type="datetime-local" id="manual_visit_datetime_input" name="manual_visit_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>" class="datetime-input" tabindex="-1">
                    <small class="help-text">Enter the actual visit date and time</small>
                </div>
                <input type="hidden" name="visit_date" value="<?php echo date('Y-m-d H:i:s'); ?>">
            </div>

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

function checkEligibility(customerId, visitType) {
    fetch(`check_eligibility.php?customer_id=${customerId}&visit_type=${visitType}`)
        .then(response => response.json())
        .then(data => {
            const errorDiv = document.getElementById('eligibility_errors');
            if (errorDiv) {
                if (data.eligible === false && data.errors && data.errors.length > 0) {
                    errorDiv.innerHTML = '<div class="alert alert-error">' + data.errors.join('<br>') + '</div>';
                } else {
                    errorDiv.innerHTML = '';
                }
            }
        })
        .catch(error => {
            console.error('Eligibility check error:', error);
        });
}

if (customerSearch) {
    customerSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            customerIdInput.value = '';
            const errorDiv = document.getElementById('eligibility_errors');
            if (errorDiv) errorDiv.innerHTML = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`customer_search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    // Don't auto-select, redirect to search page
                    if (data.length > 0) {
                        window.location.href = `customers.php?search=${encodeURIComponent(query)}`;
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }, 500);
    });
}

// Check eligibility when customer is pre-selected
<?php if ($customer): ?>
checkEligibility(<?php echo $customer['id']; ?>, 'money');
<?php endif; ?>

// Handle visit date/time override
const overrideCheckbox = document.getElementById('override_visit_date');
if (overrideCheckbox) {
    overrideCheckbox.addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('auto_visit_datetime').style.display = 'none';
            document.getElementById('manual_visit_datetime').style.display = 'block';
            document.getElementById('manual_visit_datetime_input').required = true;
        } else {
            document.getElementById('auto_visit_datetime').style.display = 'block';
            document.getElementById('manual_visit_datetime').style.display = 'none';
            document.getElementById('manual_visit_datetime_input').required = false;
        }
    });
}
</script>

<?php include 'footer.php'; ?>
