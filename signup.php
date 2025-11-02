<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    
    try {
        $db->beginTransaction();
        
        // Insert customer with automatic signup timestamp
        $signup_timestamp = date('Y-m-d H:i:s'); // Current system date and time
        $stmt = $db->prepare("INSERT INTO customers (signup_date, name, spouse, address, city, state, zip, phone, description_of_need, applied_before) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $signup_timestamp,
            $_POST['name'],
            !empty($_POST['spouse']) ? $_POST['spouse'] : null,
            $_POST['address'],
            $_POST['city'],
            $_POST['state'],
            $_POST['zip'],
            $_POST['phone'],
            $_POST['description_of_need'],
            $_POST['applied_before']
        ]);
        
        $customer_id = $db->lastInsertId();
        
        // Handle previous applications
        if ($_POST['applied_before'] === 'yes' && !empty($_POST['prev_app_date']) && !empty($_POST['prev_app_name'])) {
            $stmt = $db->prepare("INSERT INTO previous_applications (customer_id, application_date, name_used) VALUES (?, ?, ?)");
            $stmt->execute([$customer_id, $_POST['prev_app_date'], $_POST['prev_app_name']]);
        }
        
        // Handle household members
        if (!empty($_POST['household_names'])) {
            $stmt = $db->prepare("INSERT INTO household_members (customer_id, name, birthdate, relationship) VALUES (?, ?, ?, ?)");
            foreach ($_POST['household_names'] as $index => $name) {
                if (!empty($name) && !empty($_POST['household_birthdates'][$index]) && !empty($_POST['household_relationships'][$index])) {
                    $stmt->execute([
                        $customer_id,
                        $name,
                        $_POST['household_birthdates'][$index],
                        $_POST['household_relationships'][$index]
                    ]);
                }
            }
        }
        
        // Handle subsidized housing
        $stmt = $db->prepare("INSERT INTO subsidized_housing (customer_id, in_subsidized_housing, housing_date, name_used) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $customer_id,
            $_POST['subsidized_housing'],
            ($_POST['subsidized_housing'] === 'yes' && !empty($_POST['housing_date'])) ? $_POST['housing_date'] : null,
            ($_POST['subsidized_housing'] === 'yes' && !empty($_POST['housing_name'])) ? $_POST['housing_name'] : null
        ]);
        
        // Handle income
        $child_support = floatval($_POST['child_support'] ?? 0);
        $pension = floatval($_POST['pension'] ?? 0);
        $wages = floatval($_POST['wages'] ?? 0);
        $ss_ssd_ssi = floatval($_POST['ss_ssd_ssi'] ?? 0);
        $unemployment = floatval($_POST['unemployment'] ?? 0);
        $food_stamps = floatval($_POST['food_stamps'] ?? 0);
        $other = floatval($_POST['other'] ?? 0);
        $total = $child_support + $pension + $wages + $ss_ssd_ssi + $unemployment + $food_stamps + $other;
        
        $stmt = $db->prepare("INSERT INTO income_sources (customer_id, child_support, pension, wages, ss_ssd_ssi, unemployment, food_stamps, other, other_description, total_household_income) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $customer_id,
            $child_support,
            $pension,
            $wages,
            $ss_ssd_ssi,
            $unemployment,
            $food_stamps,
            $other,
            $_POST['other_description'] ?? null,
            $total
        ]);
        
        $db->commit();
        $success = "Customer successfully registered! <a href='customer_view.php?id=" . $customer_id . "'>View customer details</a>";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$page_title = "New Customer Signup";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>New Customer Signup</h1>
        <p class="lead">Register a new customer for food distribution services</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="signup-form">
        <div class="form-section">
            <h2>Basic Information</h2>
            
            <div class="form-group">
                <label>Sign Up Date & Time</label>
                <input type="text" value="<?php echo date('F d, Y \a\t g:i A'); ?>" readonly class="readonly-field">
                <small class="help-text">Automatically recorded from system time</small>
            </div>
            
            <div class="form-group">
                <label for="name">Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="spouse">Spouse (if applicable)</label>
                <input type="text" id="spouse" name="spouse">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="address">Address <span class="required">*</span></label>
                    <input type="text" id="address" name="address" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="city">City <span class="required">*</span></label>
                    <input type="text" id="city" name="city" required>
                </div>
                
                <div class="form-group">
                    <label for="state">State <span class="required">*</span></label>
                    <input type="text" id="state" name="state" maxlength="2" placeholder="XX" required>
                </div>
                
                <div class="form-group">
                    <label for="zip">ZIP <span class="required">*</span></label>
                    <input type="text" id="zip" name="zip" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number <span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" required>
            </div>
            
            <div class="form-group">
                <label for="description_of_need">Description of Need and Situation</label>
                <textarea id="description_of_need" name="description_of_need" rows="4"></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2>Previous Applications</h2>
            
            <div class="form-group">
                <label for="applied_before">Have you ever applied for assistance before? <span class="required">*</span></label>
                <select id="applied_before" name="applied_before" required>
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
            </div>
            
            <div id="previous_app_details" style="display: none;">
                <div class="form-group">
                    <label for="prev_app_date">When?</label>
                    <input type="date" id="prev_app_date" name="prev_app_date">
                </div>
                
                <div class="form-group">
                    <label for="prev_app_name">What name was used?</label>
                    <input type="text" id="prev_app_name" name="prev_app_name">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Household Members</h2>
            <p class="help-text">List all persons living in the household (name, birthdate, relationship)</p>
            
            <div id="household_members">
                <!-- Household members will be added here when user clicks "Add Household Member" -->
            </div>
            <button type="button" class="btn btn-secondary btn-small" onclick="addHouseholdMember()">+ Add Household Member</button>
        </div>

        <div class="form-section">
            <h2>Subsidized Housing</h2>
            
            <div class="form-group">
                <label for="subsidized_housing">Are you in subsidized housing? <span class="required">*</span></label>
                <select id="subsidized_housing" name="subsidized_housing" required>
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
            </div>
            
            <div id="housing_details" style="display: none;">
                <div class="form-group">
                    <label for="housing_date">When?</label>
                    <input type="date" id="housing_date" name="housing_date">
                </div>
                
                <div class="form-group">
                    <label for="housing_name">What name was used?</label>
                    <input type="text" id="housing_name" name="housing_name">
                </div>
            </div>
        </div>

        <div class="form-section">
            <h2>Total Household Income</h2>
            <p class="help-text">Enter monthly income amounts for each source</p>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="child_support">Child Support</label>
                    <input type="number" id="child_support" name="child_support" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="pension">Pension</label>
                    <input type="number" id="pension" name="pension" step="0.01" min="0" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="wages">Wages</label>
                    <input type="number" id="wages" name="wages" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="ss_ssd_ssi">SS/SSD/SSI</label>
                    <input type="number" id="ss_ssd_ssi" name="ss_ssd_ssi" step="0.01" min="0" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="unemployment">Unemployment</label>
                    <input type="number" id="unemployment" name="unemployment" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="food_stamps">Food Stamps</label>
                    <input type="number" id="food_stamps" name="food_stamps" step="0.01" min="0" value="0">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="other">Other</label>
                    <input type="number" id="other" name="other" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="other_description">Other Description</label>
                    <input type="text" id="other_description" name="other_description" placeholder="Describe other income">
                </div>
            </div>
            
            <div class="form-group">
                <label>Total Household Income</label>
                <input type="text" id="total_income" readonly value="$0.00" class="total-display">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-large">Submit Registration</button>
            <a href="index.php" class="btn btn-secondary btn-large">Cancel</a>
        </div>
    </form>
</div>

<script>
document.getElementById('applied_before').addEventListener('change', function() {
    document.getElementById('previous_app_details').style.display = this.value === 'yes' ? 'block' : 'none';
});

document.getElementById('subsidized_housing').addEventListener('change', function() {
    document.getElementById('housing_details').style.display = this.value === 'yes' ? 'block' : 'none';
});

function addHouseholdMember() {
    const container = document.getElementById('household_members');
    const member = document.createElement('div');
    member.className = 'household-member';
    member.innerHTML = `
        <div class="form-row">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="household_names[]">
            </div>
            <div class="form-group">
                <label>Birthdate</label>
                <input type="date" name="household_birthdates[]">
            </div>
            <div class="form-group">
                <label>Relationship</label>
                <input type="text" name="household_relationships[]" placeholder="e.g., Son, Daughter, Spouse">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="btn btn-small btn-danger" onclick="this.closest('.household-member').remove();">Remove</button>
            </div>
        </div>
    `;
    container.appendChild(member);
}

function updateTotalIncome() {
    const fields = ['child_support', 'pension', 'wages', 'ss_ssd_ssi', 'unemployment', 'food_stamps', 'other'];
    let total = 0;
    fields.forEach(field => {
        total += parseFloat(document.getElementById(field).value) || 0;
    });
    document.getElementById('total_income').value = '$' + total.toFixed(2);
}

['child_support', 'pension', 'wages', 'ss_ssd_ssi', 'unemployment', 'food_stamps', 'other'].forEach(field => {
    document.getElementById(field).addEventListener('input', updateTotalIncome);
});
</script>

<?php include 'footer.php'; ?>

