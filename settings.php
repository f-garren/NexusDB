<?php
require_once 'config.php';

$error = '';
$success = '';
$mode = getSetting('settings_mode', 'simple');
$current_timezone = getSetting('timezone', 'America/Boise');
$organization_name = getSetting('organization_name', 'NexusDB');
$shop_name = getSetting('shop_name', 'Partner Store');
$customer_term = getSetting('customer_term', 'Customer');
$customer_term_plural = getSetting('customer_term_plural', 'Customers');
$money_limit = intval(getSetting('money_distribution_limit', 3));
$theme_color = getSetting('theme_color', '#2c5aa0');
$voucher_prefix = getSetting('voucher_prefix', 'VCH-');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // Save mode
        if (isset($_POST['mode'])) {
            setSetting('settings_mode', $_POST['mode']);
            $mode = $_POST['mode'];
        }
        
        // Save simple mode settings
        if ($mode === 'simple' || isset($_POST['save_simple'])) {
            if (isset($_POST['timezone'])) {
                setSetting('timezone', $_POST['timezone']);
            }
            
            if (isset($_POST['organization_name'])) {
                setSetting('organization_name', $_POST['organization_name']);
            }
            
            if (isset($_POST['visits_per_month_limit'])) {
                setSetting('visits_per_month_limit', intval($_POST['visits_per_month_limit']));
            }
            
            if (isset($_POST['visits_per_year_limit'])) {
                setSetting('visits_per_year_limit', intval($_POST['visits_per_year_limit']));
            }
            
            if (isset($_POST['min_days_between_visits'])) {
                setSetting('min_days_between_visits', intval($_POST['min_days_between_visits']));
            }
            
            if (isset($_POST['shop_name'])) {
                setSetting('shop_name', $_POST['shop_name']);
            }
            
            if (isset($_POST['customer_term'])) {
                setSetting('customer_term', $_POST['customer_term']);
            }
            
            if (isset($_POST['customer_term_plural'])) {
                setSetting('customer_term_plural', $_POST['customer_term_plural']);
            }
            
            if (isset($_POST['money_distribution_limit'])) {
                setSetting('money_distribution_limit', intval($_POST['money_distribution_limit']));
            }
            
            if (isset($_POST['theme_color'])) {
                setSetting('theme_color', $_POST['theme_color']);
            }
            
            if (isset($_POST['voucher_prefix'])) {
                setSetting('voucher_prefix', $_POST['voucher_prefix']);
            }
        }
        
        // Save advanced mode settings
        if ($mode === 'advanced' || isset($_POST['save_advanced'])) {
            if (isset($_POST['timezone'])) {
                setSetting('timezone', $_POST['timezone']);
            }
            
            if (isset($_POST['organization_name'])) {
                setSetting('organization_name', $_POST['organization_name']);
            }
            
            if (isset($_POST['visits_per_month_limit'])) {
                setSetting('visits_per_month_limit', intval($_POST['visits_per_month_limit']));
            }
            
            if (isset($_POST['visits_per_year_limit'])) {
                setSetting('visits_per_year_limit', intval($_POST['visits_per_year_limit']));
            }
            
            if (isset($_POST['min_days_between_visits'])) {
                setSetting('min_days_between_visits', intval($_POST['min_days_between_visits']));
            }
            
            if (isset($_POST['shop_name'])) {
                setSetting('shop_name', $_POST['shop_name']);
            }
            
            if (isset($_POST['customer_term'])) {
                setSetting('customer_term', $_POST['customer_term']);
            }
            
            if (isset($_POST['customer_term_plural'])) {
                setSetting('customer_term_plural', $_POST['customer_term_plural']);
            }
            
            if (isset($_POST['money_distribution_limit'])) {
                setSetting('money_distribution_limit', intval($_POST['money_distribution_limit']));
            }
            
            if (isset($_POST['theme_color'])) {
                setSetting('theme_color', $_POST['theme_color']);
            }
            
            if (isset($_POST['voucher_prefix'])) {
                setSetting('voucher_prefix', $_POST['voucher_prefix']);
            }
        }
        
        // Reload timezone if it was updated
        if (isset($_POST['timezone'])) {
            date_default_timezone_set($_POST['timezone']);
            $current_timezone = $_POST['timezone'];
        }
        
        // Update organization name if changed
        if (isset($_POST['organization_name'])) {
            $organization_name = $_POST['organization_name'];
        }
        
        $success = "Settings saved successfully!";
        
    } catch (Exception $e) {
        $error = "Error saving settings: " . $e->getMessage();
    }
}
$visits_per_month = getSetting('visits_per_month_limit', 2);
$visits_per_year = getSetting('visits_per_year_limit', 12);
$min_days_between = getSetting('min_days_between_visits', 14);

// Get list of timezones
$timezones = timezone_identifiers_list();

$page_title = "Settings";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Settings</h1>
        <p class="lead">Configure application settings</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <!-- Mode Toggle - Hidden for now but functionality kept for future -->
    <div class="mode-toggle" style="display: none;">
        <form method="POST" action="" style="display: inline;">
            <input type="hidden" name="mode" value="<?php echo $mode === 'simple' ? 'advanced' : 'simple'; ?>">
            <button type="submit" class="btn <?php echo $mode === 'simple' ? 'btn-secondary' : 'btn-primary'; ?>">
                <?php echo $mode === 'simple' ? 'Switch to Advanced Mode' : 'Switch to Simple Mode'; ?>
            </button>
        </form>
        <span class="current-mode">Current Mode: <strong><?php echo ucfirst($mode); ?></strong></span>
    </div>

    <form method="POST" action="" class="settings-form">
        <?php if ($mode === 'simple'): ?>
            <!-- Simple Mode with Categories -->
            <div class="settings-section">
                <div class="settings-categories">
                    <div class="settings-category active" data-category="general">
                        <h3><ion-icon name="settings"></ion-icon> General</h3>
                    </div>
                    <div class="settings-category" data-category="terminology">
                        <h3><ion-icon name="text"></ion-icon> Terminology</h3>
                    </div>
                    <div class="settings-category" data-category="limits">
                        <h3><ion-icon name="speedometer"></ion-icon> Visit Limits</h3>
                    </div>
                    <div class="settings-category" data-category="appearance">
                        <h3><ion-icon name="color-palette"></ion-icon> Appearance</h3>
                    </div>
                </div>
                
                <div class="settings-content">
                    <!-- General Settings -->
                    <div class="category-content active" id="category-general">
                        <h2>General Settings</h2>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone <span class="required">*</span></label>
                            <select id="timezone" name="timezone" required>
                                <?php
                                foreach ($timezones as $tz) {
                                    $selected = ($tz === $current_timezone) ? 'selected' : '';
                                    echo "<option value=\"$tz\" $selected>$tz</option>";
                                }
                                ?>
                            </select>
                            <small class="help-text">Current timezone: <?php echo date_default_timezone_get(); ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="organization_name">Organization Name</label>
                            <input type="text" id="organization_name" name="organization_name" value="<?php echo htmlspecialchars($organization_name); ?>" placeholder="Enter organization name">
                            <small class="help-text">This name will be displayed throughout the application</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="shop_name">Shop/Store Name (for Voucher Redemption)</label>
                            <input type="text" id="shop_name" name="shop_name" value="<?php echo htmlspecialchars($shop_name); ?>" placeholder="Enter shop/store name">
                            <small class="help-text">Name of the shop where vouchers can be redeemed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="voucher_prefix">Voucher Code Prefix</label>
                            <input type="text" id="voucher_prefix" name="voucher_prefix" value="<?php echo htmlspecialchars($voucher_prefix); ?>" placeholder="e.g., VCH-, VOUCHER-, etc." maxlength="20">
                            <small class="help-text">Prefix used for all voucher codes (e.g., "VCH-" results in codes like "VCH-XXXXXXXX")</small>
                        </div>
                    </div>
                    
                    <!-- Terminology Settings -->
                    <div class="category-content" id="category-terminology">
                        <h2>Terminology Settings</h2>
                        
                        <div class="form-group">
                            <label for="customer_term"><?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> Term (Singular)</label>
                            <input type="text" id="customer_term" name="customer_term" value="<?php echo htmlspecialchars($customer_term); ?>" placeholder="e.g., Customer, Client, Participant">
                            <small class="help-text">Term used throughout the application (singular form)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="customer_term_plural"><?php echo htmlspecialchars(getCustomerTerm('Customer')); ?> Term (Plural)</label>
                            <input type="text" id="customer_term_plural" name="customer_term_plural" value="<?php echo htmlspecialchars($customer_term_plural); ?>" placeholder="e.g., Customers, Clients, Participants">
                            <small class="help-text">Term used throughout the application (plural form)</small>
                        </div>
                    </div>
                    
                    <!-- Visit Limits Settings -->
                    <div class="category-content" id="category-limits">
                        <h2>Visit Limits</h2>
                        
                        <div class="form-section">
                            <h3>Food Visit Limits</h3>
                            
                            <div class="form-group">
                                <label for="visits_per_month_limit">Food Visits Per Month Limit</label>
                                <input type="number" id="visits_per_month_limit" name="visits_per_month_limit" value="<?php echo $visits_per_month; ?>" min="1" required>
                                <small class="help-text">Maximum number of food visits allowed per customer per month</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="visits_per_year_limit">Food Visits Per Year Limit</label>
                                <input type="number" id="visits_per_year_limit" name="visits_per_year_limit" value="<?php echo $visits_per_year; ?>" min="1" required>
                                <small class="help-text">Maximum number of food visits allowed per customer per year</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_days_between_visits">Minimum Days Between Food Visits</label>
                                <input type="number" id="min_days_between_visits" name="min_days_between_visits" value="<?php echo $min_days_between; ?>" min="1" required>
                                <small class="help-text">Minimum number of days that must pass between food visits</small>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Money Distribution Limit</h3>
                            
                            <div class="form-group">
                                <label for="money_distribution_limit">Money Distribution Limit Per Household</label>
                                <input type="number" id="money_distribution_limit" name="money_distribution_limit" value="<?php echo $money_limit; ?>" min="1" required>
                                <small class="help-text">Maximum number of money distributions allowed per household (all time). Note: Vouchers have no limit.</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appearance Settings -->
                    <div class="category-content" id="category-appearance">
                        <h2>Appearance & Theme</h2>
                        
                        <div class="form-group">
                            <label for="theme_color">Theme Color (Hex Code)</label>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <input type="color" id="theme_color" name="theme_color" value="<?php echo htmlspecialchars($theme_color); ?>" style="width: 100px; height: 40px;">
                                <input type="text" id="theme_color_text" value="<?php echo htmlspecialchars($theme_color); ?>" style="width: 120px; padding: 0.5rem;" placeholder="#2c5aa0" pattern="^#[0-9A-Fa-f]{6}$">
                            </div>
                            <small class="help-text">Primary color used throughout the application (click color picker or enter hex code like #2c5aa0)</small>
                            <script>
                                document.getElementById('theme_color').addEventListener('input', function() {
                                    document.getElementById('theme_color_text').value = this.value;
                                });
                                document.getElementById('theme_color_text').addEventListener('input', function() {
                                    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                                        document.getElementById('theme_color').value = this.value;
                                    }
                                });
                            </script>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 2rem; border-top: 2px solid var(--border-color); padding-top: 2rem;">
                    <button type="submit" name="save_simple" class="btn btn-primary btn-large">Save All Settings</button>
                </div>
                
                <script>
                    // Category switching
                    document.querySelectorAll('.settings-category').forEach(cat => {
                        cat.addEventListener('click', function() {
                            const category = this.dataset.category;
                            
                            // Update active states
                            document.querySelectorAll('.settings-category').forEach(c => c.classList.remove('active'));
                            document.querySelectorAll('.category-content').forEach(c => c.classList.remove('active'));
                            
                            this.classList.add('active');
                            document.getElementById('category-' + category).classList.add('active');
                        });
                    });
                </script>
            </div>
        <?php else: ?>
            <!-- Advanced Mode -->
            <div class="settings-section">
                <h2>Advanced Settings</h2>
                
                <div class="form-section">
                    <h3>Visit Limits</h3>
                    
                    <div class="form-group">
                        <label for="visits_per_month_limit">Visits Per Month Limit</label>
                        <input type="number" id="visits_per_month_limit" name="visits_per_month_limit" value="<?php echo $visits_per_month; ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="visits_per_year_limit">Visits Per Year Limit</label>
                        <input type="number" id="visits_per_year_limit" name="visits_per_year_limit" value="<?php echo $visits_per_year; ?>" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_days_between_visits">Minimum Days Between Visits</label>
                        <input type="number" id="min_days_between_visits" name="min_days_between_visits" value="<?php echo $min_days_between; ?>" min="1" required>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>System Settings</h3>
                    
                    <div class="form-group">
                        <label for="timezone">Timezone <span class="required">*</span></label>
                        <select id="timezone" name="timezone" required>
                            <?php
                            foreach ($timezones as $tz) {
                                $selected = ($tz === $current_timezone) ? 'selected' : '';
                                echo "<option value=\"$tz\" $selected>$tz</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="organization_name">Organization Name</label>
                        <input type="text" id="organization_name" name="organization_name" value="<?php echo htmlspecialchars($organization_name); ?>" placeholder="Enter organization name">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_advanced" class="btn btn-primary btn-large">Save Settings</button>
                </div>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php include 'footer.php'; ?>

