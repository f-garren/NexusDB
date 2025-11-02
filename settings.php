<?php
require_once 'config.php';

$error = '';
$success = '';
$mode = getSetting('settings_mode', 'simple');
$current_timezone = getSetting('timezone', 'America/Boise');
$organization_name = getSetting('organization_name', 'NexusDB');

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
            <!-- Simple Mode -->
            <div class="settings-section">
                <h2>Simple Settings</h2>
                
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
                
                <div class="form-section">
                    <h3>Visit Limits</h3>
                    
                    <div class="form-group">
                        <label for="visits_per_month_limit">Visits Per Month Limit</label>
                        <input type="number" id="visits_per_month_limit" name="visits_per_month_limit" value="<?php echo $visits_per_month; ?>" min="1" required>
                        <small class="help-text">Maximum number of visits allowed per customer per month</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="visits_per_year_limit">Visits Per Year Limit</label>
                        <input type="number" id="visits_per_year_limit" name="visits_per_year_limit" value="<?php echo $visits_per_year; ?>" min="1" required>
                        <small class="help-text">Maximum number of visits allowed per customer per year</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_days_between_visits">Minimum Days Between Visits</label>
                        <input type="number" id="min_days_between_visits" name="min_days_between_visits" value="<?php echo $min_days_between; ?>" min="1" required>
                        <small class="help-text">Minimum number of days that must pass between customer visits</small>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="save_simple" class="btn btn-primary btn-large">Save Settings</button>
                </div>
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

