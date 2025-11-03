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
        
        // Use current timestamp or manual override for visit
        if (!empty($p['override_visit_date']) && !empty($p['manual_visit_datetime'])) {
            $visit_date = date('Y-m-d H:i:s', strtotime($p['manual_visit_datetime']));
            $visit_timestamp = strtotime($p['manual_visit_datetime']);
        } else {
            $visit_timestamp = time();
            $visit_date = date('Y-m-d H:i:s');
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
        $voucher_code = 'VCH-' . strtoupper(substr(md5(time() . $customer_id . rand()), 0, 8));
        
        // Check if code exists (unlikely but possible)
        $stmt = $db->prepare("SELECT id FROM vouchers WHERE voucher_code = ?");
        $stmt->execute([$voucher_code]);
        while ($stmt->fetch()) {
            $voucher_code = 'VCH-' . strtoupper(substr(md5(time() . $customer_id . rand()), 0, 8));
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
                    <tr><th>Visit Date & Time:</th><td><?php 
                        if (!empty($form_data['override_visit_date']) && !empty($form_data['manual_visit_datetime'])) {
                            echo date('F d, Y \a\t g:i A', strtotime($form_data['manual_visit_datetime']));
                        } else {
                            echo date('F d, Y \a\t g:i A');
                        }
                    ?></td></tr>
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
            <div class="form-group">
                <label for="customer_search">Search <?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> <span class="required">*</span></label>
                <input type="text" id="customer_search" placeholder="Type <?php echo strtolower(getCustomerTerm('customer')); ?> name or phone..." autocomplete="off" value="<?php echo $customer ? htmlspecialchars($customer['name']) : ''; ?>">
                <input type="hidden" id="customer_id" name="customer_id" value="<?php echo $customer ? $customer['id'] : ''; ?>" required>
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
                    <input type="datetime-local" id="manual_visit_datetime_input" name="manual_visit_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>">
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

if (customerSearch) {
    customerSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();
        
        if (query.length < 2) {
            customerIdInput.value = '';
            return;
        }
        
        searchTimeout = setTimeout(() => {
            fetch(`customer_search.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 1) {
                        const customer = data[0];
                        customerIdInput.value = customer.id;
                        customerSearch.value = customer.name;
                        window.location.href = `visits_voucher.php?customer_id=${customer.id}`;
                    } else if (data.length > 1) {
                        window.location.href = `customers.php?search=${encodeURIComponent(query)}`;
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }, 500);
    });
}

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
