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

// Handle backup and restore operations FIRST (before main form submission)
$BACKUP_DIR = dirname(__FILE__) . '/backups';

// Create backup directory if it doesn't exist
if (!file_exists($BACKUP_DIR)) {
    mkdir($BACKUP_DIR, 0755, true);
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    try {
        $db = getDB();
        $timestamp = date('Y-m-d_His');
        $version = defined('APP_VERSION') ? APP_VERSION : 'unknown';
        // Format version for filename (replace dots with underscores)
        $version_safe = str_replace('.', '_', $version);
        $backup_filename = "nexusdb_backup_v{$version_safe}_{$timestamp}.sql";
        $backup_path = $BACKUP_DIR . '/' . $backup_filename;
        
        // Get database credentials
        $db_host = DB_HOST;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        $db_name = DB_NAME;
        
        // Create backup using mysqldump
        // Redirect stdout to backup file and stderr to /dev/null separately to prevent warnings from polluting the SQL file
        $command = "mysqldump -h {$db_host} -u {$db_user} -p{$db_pass} {$db_name} > " . escapeshellarg($backup_path) . " 2>/dev/null";
        exec($command, $output, $return_code);
        
        if ($return_code === 0 && file_exists($backup_path) && filesize($backup_path) > 0) {
            $success = "Backup created successfully: {$backup_filename}";
        } else {
            $error_msg = !empty($output) ? implode("\n", $output) : "Unknown error";
            $error = "Failed to create backup. " . $error_msg;
        }
    } catch (Exception $e) {
        $error = "Error creating backup: " . $e->getMessage();
    }
}

// Handle backup download (this exits, so it must be before form processing)
if (isset($_GET['download_backup'])) {
    $backup_file = basename($_GET['download_backup']);
    $backup_path = $BACKUP_DIR . '/' . $backup_file;
    
    if (file_exists($backup_path) && pathinfo($backup_path, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $backup_file . '"');
        header('Content-Length: ' . filesize($backup_path));
        readfile($backup_path);
        exit;
    } else {
        $error = "Backup file not found or invalid.";
    }
}

// Handle backup deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    $backup_file = basename($_POST['delete_backup']);
    $backup_path = $BACKUP_DIR . '/' . $backup_file;
    
    if (file_exists($backup_path) && pathinfo($backup_path, PATHINFO_EXTENSION) === 'sql') {
        if (unlink($backup_path)) {
            $success = "Backup deleted successfully.";
        } else {
            $error = "Failed to delete backup file.";
        }
    } else {
        $error = "Backup file not found or invalid.";
    }
}

// Handle backup restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_backup'])) {
    try {
        $db = getDB();
        
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            $uploaded_file = $_FILES['restore_file']['tmp_name'];
            $file_name = $_FILES['restore_file']['name'];
            
            // Validate file type
            if (pathinfo($file_name, PATHINFO_EXTENSION) !== 'sql') {
                throw new Exception("Invalid file type. Only .sql files are allowed.");
            }
            
            // Read and validate SQL file
            $sql_content = file_get_contents($uploaded_file);
            if ($sql_content === false) {
                throw new Exception("Failed to read backup file.");
            }
            
            // Filter out mysqldump/mysql warning lines that may have been written to the file
            $lines = explode("\n", $sql_content);
            $clean_lines = [];
            foreach ($lines as $line) {
                // Skip lines that are warnings (typically start with "mysqldump:" or "mysql:")
                if (preg_match('/^(mysqldump|mysql):\s*\[Warning\]/i', $line)) {
                    continue;
                }
                $clean_lines[] = $line;
            }
            $sql_content = implode("\n", $clean_lines);
            
            // Get database credentials
            $db_host = DB_HOST;
            $db_user = DB_USER;
            $db_pass = DB_PASS;
            $db_name = DB_NAME;
            
            // Execute SQL file
            $temp_file = sys_get_temp_dir() . '/' . uniqid('restore_') . '.sql';
            file_put_contents($temp_file, $sql_content);
            
            // Redirect stderr separately to avoid mixing warnings with output
            $command = "mysql -h {$db_host} -u {$db_user} -p{$db_pass} {$db_name} < " . escapeshellarg($temp_file) . " 2>&1";
            exec($command, $output, $return_code);
            
            unlink($temp_file);
            
            // Filter out warning messages from output when checking success
            $real_errors = array_filter($output, function($line) {
                return !preg_match('/\[Warning\]/', $line);
            });
            
            if ($return_code === 0) {
                $success = "Backup restored successfully from: {$file_name}";
            } else {
                $error_msg = !empty($real_errors) ? implode("\n", $real_errors) : implode("\n", $output);
                $error = "Failed to restore backup. " . $error_msg;
            }
        } elseif (isset($_POST['restore_from_list'])) {
            $backup_file = basename($_POST['restore_from_list']);
            $backup_path = $BACKUP_DIR . '/' . $backup_file;
            
            if (file_exists($backup_path) && pathinfo($backup_path, PATHINFO_EXTENSION) === 'sql') {
                $sql_content = file_get_contents($backup_path);
                
                // Filter out mysqldump/mysql warning lines that may have been written to the file
                $lines = explode("\n", $sql_content);
                $clean_lines = [];
                foreach ($lines as $line) {
                    // Skip lines that are warnings (typically start with "mysqldump:" or "mysql:")
                    if (preg_match('/^(mysqldump|mysql):\s*\[Warning\]/i', $line)) {
                        continue;
                    }
                    $clean_lines[] = $line;
                }
                $sql_content = implode("\n", $clean_lines);
                
                // Get database credentials
                $db_host = DB_HOST;
                $db_user = DB_USER;
                $db_pass = DB_PASS;
                $db_name = DB_NAME;
                
                $temp_file = sys_get_temp_dir() . '/' . uniqid('restore_') . '.sql';
                file_put_contents($temp_file, $sql_content);
                
                // Redirect stderr separately to avoid mixing warnings with output
                $command = "mysql -h {$db_host} -u {$db_user} -p{$db_pass} {$db_name} < " . escapeshellarg($temp_file) . " 2>&1";
                exec($command, $output, $return_code);
                
                unlink($temp_file);
                
                // Filter out warning messages from output when checking success
                $real_errors = array_filter($output, function($line) {
                    return !preg_match('/\[Warning\]/', $line);
                });
                
                if ($return_code === 0) {
                    $success = "Backup restored successfully from: {$backup_file}";
                } else {
                    $error_msg = !empty($real_errors) ? implode("\n", $real_errors) : implode("\n", $output);
                    $error = "Failed to restore backup. " . $error_msg;
                }
            } else {
                $error = "Backup file not found or invalid.";
            }
        } else {
            $error = "Please select a backup file to restore.";
        }
    } catch (Exception $e) {
        $error = "Error restoring backup: " . $e->getMessage();
    }
}

// Get list of backup files
$backup_files = [];
if (is_dir($BACKUP_DIR)) {
    $files = scandir($BACKUP_DIR);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $file_path = $BACKUP_DIR . '/' . $file;
            $backup_files[] = [
                'name' => $file,
                'path' => $file_path,
                'size' => filesize($file_path),
                'date' => filemtime($file_path)
            ];
        }
    }
    // Sort by date, newest first
    usort($backup_files, function($a, $b) {
        return $b['date'] - $a['date'];
    });
}

// Handle form submission (only if not a backup operation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['create_backup']) && !isset($_POST['delete_backup']) && !isset($_POST['restore_backup'])) {
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
            
            if (isset($_POST['allowed_ips'])) {
                setSetting('allowed_ips', $_POST['allowed_ips']);
            }
            
            if (isset($_POST['allowed_dns'])) {
                setSetting('allowed_dns', $_POST['allowed_dns']);
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
            
            if (isset($_POST['money_distribution_limit_month'])) {
                setSetting('money_distribution_limit_month', intval($_POST['money_distribution_limit_month']));
            }
            
            if (isset($_POST['money_distribution_limit_year'])) {
                setSetting('money_distribution_limit_year', intval($_POST['money_distribution_limit_year']));
            }
            
            if (isset($_POST['money_min_days_between'])) {
                setSetting('money_min_days_between', intval($_POST['money_min_days_between']));
            }
            
            if (isset($_POST['voucher_limit_month'])) {
                setSetting('voucher_limit_month', intval($_POST['voucher_limit_month']));
            }
            
            if (isset($_POST['voucher_limit_year'])) {
                setSetting('voucher_limit_year', intval($_POST['voucher_limit_year']));
            }
            
            if (isset($_POST['voucher_min_days_between'])) {
                setSetting('voucher_min_days_between', intval($_POST['voucher_min_days_between']));
            }
            
            if (isset($_POST['theme_color'])) {
                setSetting('theme_color', $_POST['theme_color']);
            }
            
            if (isset($_POST['voucher_prefix'])) {
                setSetting('voucher_prefix', $_POST['voucher_prefix']);
            }
            
            if (isset($_POST['allowed_ips'])) {
                setSetting('allowed_ips', $_POST['allowed_ips']);
            }
            
            if (isset($_POST['allowed_dns'])) {
                setSetting('allowed_dns', $_POST['allowed_dns']);
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
                    <div class="settings-category" data-category="security">
                        <h3><ion-icon name="shield-checkmark"></ion-icon> Security</h3>
                    </div>
                    <div class="settings-category" data-category="backup">
                        <h3><ion-icon name="archive"></ion-icon> Backup & Restore</h3>
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
                                <input type="number" id="visits_per_month_limit" name="visits_per_month_limit" value="<?php echo $visits_per_month; ?>" min="-1" required>
                                <small class="help-text">Maximum number of food visits allowed per customer per month (use -1 for unlimited, 0 to disable)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="visits_per_year_limit">Food Visits Per Year Limit</label>
                                <input type="number" id="visits_per_year_limit" name="visits_per_year_limit" value="<?php echo $visits_per_year; ?>" min="-1" required>
                                <small class="help-text">Maximum number of food visits allowed per customer per year (use -1 for unlimited, 0 to disable)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_days_between_visits">Minimum Days Between Food Visits</label>
                                <input type="number" id="min_days_between_visits" name="min_days_between_visits" value="<?php echo $min_days_between; ?>" min="-1" required>
                                <small class="help-text">Minimum number of days that must pass between food visits (use -1 for unlimited, 0 to disable)</small>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Money Distribution Limits</h3>
                            
                            <div class="form-group">
                                <label for="money_distribution_limit">Money Distribution Limit Per Household (Total)</label>
                                <input type="number" id="money_distribution_limit" name="money_distribution_limit" value="<?php echo $money_limit; ?>" min="-1" required>
                                <small class="help-text">Maximum number of money distributions allowed per household (all time). Use -1 for unlimited, 0 to disable.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="money_distribution_limit_month">Money Distribution Limit Per Month</label>
                                <input type="number" id="money_distribution_limit_month" name="money_distribution_limit_month" value="<?php echo intval(getSetting('money_distribution_limit_month', -1)); ?>" min="-1" required>
                                <small class="help-text">Maximum number of money distributions allowed per household per month. Use -1 for unlimited, 0 to disable.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="money_distribution_limit_year">Money Distribution Limit Per Year</label>
                                <input type="number" id="money_distribution_limit_year" name="money_distribution_limit_year" value="<?php echo intval(getSetting('money_distribution_limit_year', -1)); ?>" min="-1" required>
                                <small class="help-text">Maximum number of money distributions allowed per household per year. Use -1 for unlimited, 0 to disable.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="money_min_days_between">Minimum Days Between Money Visits</label>
                                <input type="number" id="money_min_days_between" name="money_min_days_between" value="<?php echo intval(getSetting('money_min_days_between', -1)); ?>" min="-1" required>
                                <small class="help-text">Minimum number of days that must pass between money visits. Use -1 for unlimited, 0 to disable.</small>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Voucher Limits</h3>
                            
                            <div class="form-group">
                                <label for="voucher_limit_month">Voucher Limit Per Month</label>
                                <input type="number" id="voucher_limit_month" name="voucher_limit_month" value="<?php echo intval(getSetting('voucher_limit_month', -1)); ?>" min="-1" required>
                                <small class="help-text">Maximum number of vouchers allowed per customer per month. Use -1 for unlimited, 0 to disable.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="voucher_limit_year">Voucher Limit Per Year</label>
                                <input type="number" id="voucher_limit_year" name="voucher_limit_year" value="<?php echo intval(getSetting('voucher_limit_year', -1)); ?>" min="-1" required>
                                <small class="help-text">Maximum number of vouchers allowed per customer per year. Use -1 for unlimited, 0 to disable.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="voucher_min_days_between">Minimum Days Between Voucher Visits</label>
                                <input type="number" id="voucher_min_days_between" name="voucher_min_days_between" value="<?php echo intval(getSetting('voucher_min_days_between', -1)); ?>" min="-1" required>
                                <small class="help-text">Minimum number of days that must pass between voucher visits. Use -1 for unlimited, 0 to disable.</small>
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
                    
                    <!-- Security Settings -->
                    <div class="category-content" id="category-security">
                        <h2>Access Control</h2>
                        
                        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                            <ion-icon name="warning"></ion-icon>
                            <strong>Warning:</strong> Access control restrictions will prevent unauthorized users from accessing the system. 
                            Leave empty to allow access from any IP/DNS. Changes take effect immediately.
                        </div>
                        
                        <div class="form-group">
                            <label for="allowed_ips">Allowed IP Addresses</label>
                            <textarea id="allowed_ips" name="allowed_ips" rows="4" placeholder="192.168.1.100, 10.0.0.0/24, 172.16.0.1"><?php echo htmlspecialchars(getSetting('allowed_ips', '')); ?></textarea>
                            <small class="help-text">Comma-separated list of allowed IP addresses. Supports CIDR notation (e.g., 192.168.1.0/24). Leave empty to allow all IPs.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="allowed_dns">Allowed DNS Names</label>
                            <textarea id="allowed_dns" name="allowed_dns" rows="4" placeholder="example.com, subdomain.example.org"><?php echo htmlspecialchars(getSetting('allowed_dns', '')); ?></textarea>
                            <small class="help-text">Comma-separated list of allowed DNS names or domains. Leave empty to allow all DNS names.</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Current IP Address</label>
                            <input type="text" value="<?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?>" readonly class="readonly-field">
                            <small class="help-text">Your current IP address. Add this to the allowed IPs list if you want to ensure continued access.</small>
                        </div>
                    </div>
                    
                    <!-- Backup & Restore Settings -->
                    <div class="category-content" id="category-backup">
                        <h2>Backup & Restore</h2>
                        
                        <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                            <ion-icon name="warning"></ion-icon>
                            <strong>Warning:</strong> Restoring a backup will replace all current data in the database. 
                            Make sure to create a backup before restoring if needed.
                        </div>
                        
                        <div class="form-section">
                            <h3>Create Backup</h3>
                            <p>Create a complete backup of your database. Backups are saved locally and can be downloaded or restored later.</p>
                            <form method="POST" action="" style="display: inline;">
                                <button type="submit" name="create_backup" class="btn btn-primary" onclick="return confirm('Create a new backup of the database?');">
                                    <ion-icon name="download"></ion-icon> Create Backup Now
                                </button>
                            </form>
                        </div>
                        
                        <div class="form-section" style="margin-top: 2rem;">
                            <h3>Existing Backups</h3>
                            <?php if (empty($backup_files)): ?>
                                <p class="no-data">No backups found. Create your first backup above.</p>
                            <?php else: ?>
                                <table class="data-table" style="margin-top: 1rem;">
                                    <thead>
                                        <tr>
                                            <th>Backup File</th>
                                            <th>Date Created</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backup_files as $backup): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i:s', $backup['date']); ?></td>
                                                <td><?php echo number_format($backup['size'] / 1024, 2); ?> KB</td>
                                                <td>
                                                    <a href="?download_backup=<?php echo urlencode($backup['name']); ?>" class="btn btn-small btn-primary">
                                                        <ion-icon name="download-outline"></ion-icon> Download
                                                    </a>
                                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to restore this backup? This will replace all current data.');">
                                                        <input type="hidden" name="restore_from_list" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                        <button type="submit" name="restore_backup" class="btn btn-small" style="background-color: var(--primary-color); color: white;">
                                                            <ion-icon name="refresh"></ion-icon> Restore
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this backup?');">
                                                        <input type="hidden" name="delete_backup" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                        <button type="submit" class="btn btn-small" style="background-color: var(--danger-color); color: white;">
                                                            <ion-icon name="trash"></ion-icon> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-section" style="margin-top: 2rem;">
                            <h3>Restore from Upload</h3>
                            <p>Upload a .sql backup file to restore your database.</p>
                            <form method="POST" action="" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to restore this backup? This will replace all current data.');">
                                <div class="form-group">
                                    <label for="restore_file">Select Backup File (.sql)</label>
                                    <input type="file" id="restore_file" name="restore_file" accept=".sql" required>
                                    <small class="help-text">Select a .sql backup file to restore</small>
                                </div>
                                <button type="submit" name="restore_backup" class="btn btn-primary">
                                    <ion-icon name="cloud-upload"></ion-icon> Upload and Restore
                                </button>
                            </form>
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
                        <input type="number" id="visits_per_month_limit" name="visits_per_month_limit" value="<?php echo $visits_per_month; ?>" min="-1" required>
                        <small class="help-text">Use -1 for unlimited, 0 to disable</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="visits_per_year_limit">Visits Per Year Limit</label>
                        <input type="number" id="visits_per_year_limit" name="visits_per_year_limit" value="<?php echo $visits_per_year; ?>" min="-1" required>
                        <small class="help-text">Use -1 for unlimited, 0 to disable</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_days_between_visits">Minimum Days Between Visits</label>
                        <input type="number" id="min_days_between_visits" name="min_days_between_visits" value="<?php echo $min_days_between; ?>" min="-1" required>
                        <small class="help-text">Use -1 for unlimited, 0 to disable</small>
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

