<?php
require_once 'config.php';

$customer_id = $_GET['id'] ?? 0;
$db = getDB();

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: customers.php');
    exit;
}

// Get household members
$stmt = $db->prepare("SELECT * FROM household_members WHERE customer_id = ? ORDER BY birthdate");
$stmt->execute([$customer_id]);
$household = $stmt->fetchAll();

// Get previous applications
$stmt = $db->prepare("SELECT * FROM previous_applications WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$prev_apps = $stmt->fetchAll();

// Get subsidized housing
$stmt = $db->prepare("SELECT * FROM subsidized_housing WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$housing = $stmt->fetch();

// Get income
$stmt = $db->prepare("SELECT * FROM income_sources WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$income = $stmt->fetch();

// Get visits
$stmt = $db->prepare("SELECT * FROM visits WHERE customer_id = ? ORDER BY visit_date DESC");
$stmt->execute([$customer_id]);
$visits = $stmt->fetchAll();

// Count visits by type
$stmt = $db->prepare("SELECT visit_type, COUNT(*) as count FROM visits WHERE customer_id = ? GROUP BY visit_type");
$stmt->execute([$customer_id]);
$visit_counts = [];
while ($row = $stmt->fetch()) {
    $visit_counts[$row['visit_type']] = $row['count'];
}

// Get visit limits
$visits_per_month = getSetting('visits_per_month_limit', 2);
$visits_per_year = getSetting('visits_per_year_limit', 12);
$min_days_between = getSetting('min_days_between_visits', 14);

// Calculate visit statistics
$current_month_visits = 0;
$current_year_visits = 0;
$last_visit_date = null;

foreach ($visits as $visit) {
    $visit_date = strtotime($visit['visit_date']);
    $now = time();
    
    if (date('Y-m', $visit_date) === date('Y-m', $now)) {
        $current_month_visits++;
    }
    if (date('Y', $visit_date) === date('Y', $now)) {
        $current_year_visits++;
    }
    
    if ($last_visit_date === null || $visit_date > $last_visit_date) {
        $last_visit_date = $visit_date;
    }
}

$days_since_last_visit = $last_visit_date ? floor((time() - $last_visit_date) / 86400) : null;

$page_title = "Customer Details";
include 'header.php';
?>

<div class="container">
    <div class="page-header">
        <div class="header-actions">
            <a href="customers.php" class="btn btn-secondary">← Back to Customers</a>
            <a href="visits.php?customer_id=<?php echo $customer_id; ?>" class="btn btn-primary">Record Visit</a>
        </div>
        <h1><?php echo htmlspecialchars($customer['name']); ?></h1>
    </div>

    <!-- Visit Status Alert -->
    <?php if ($days_since_last_visit !== null): ?>
        <?php if ($days_since_last_visit < $min_days_between): ?>
            <div class="alert alert-warning">
                ⚠️ Last visit was <?php echo $days_since_last_visit; ?> days ago. Minimum <?php echo $min_days_between; ?> days required between visits.
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($current_month_visits >= $visits_per_month): ?>
        <div class="alert alert-error">
            ❌ Monthly visit limit reached (<?php echo $current_month_visits; ?>/<?php echo $visits_per_month; ?>)
        </div>
    <?php elseif ($current_month_visits > 0): ?>
        <div class="alert alert-info">
            ℹ️ <?php echo $current_month_visits; ?>/<?php echo $visits_per_month; ?> visits this month
        </div>
    <?php endif; ?>
    
    <?php if ($current_year_visits >= $visits_per_year): ?>
        <div class="alert alert-error">
            ❌ Yearly visit limit reached (<?php echo $current_year_visits; ?>/<?php echo $visits_per_year; ?>)
        </div>
    <?php elseif ($current_year_visits > 0): ?>
        <div class="alert alert-info">
            ℹ️ <?php echo $current_year_visits; ?>/<?php echo $visits_per_year; ?> visits this year
        </div>
    <?php endif; ?>

    <div class="customer-details-grid">
        <div class="detail-section">
            <h2>Basic Information</h2>
            <table class="info-table">
                <tr>
                    <th>Signup Date & Time:</th>
                    <td><?php echo date('F d, Y \a\t g:i A', strtotime($customer['signup_date'])); ?></td>
                </tr>
                <tr>
                    <th>Name:</th>
                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                </tr>
                <tr>
                    <th>Address:</th>
                    <td><?php echo htmlspecialchars($customer['address']); ?></td>
                </tr>
                <tr>
                    <th>City, State, ZIP:</th>
                    <td><?php echo htmlspecialchars($customer['city'] . ', ' . $customer['state'] . ' ' . $customer['zip']); ?></td>
                </tr>
                <tr>
                    <th>Phone:</th>
                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                </tr>
                <?php if ($customer['description_of_need']): ?>
                <tr>
                    <th>Description of Need:</th>
                    <td><?php echo nl2br(htmlspecialchars($customer['description_of_need'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <?php if (count($household) > 0): ?>
        <div class="detail-section">
            <h2>Household Members</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Birthdate</th>
                        <th>Relationship</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($household as $member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($member['name']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($member['birthdate'])); ?></td>
                            <td><?php echo htmlspecialchars($member['relationship']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($customer['applied_before'] === 'yes' && count($prev_apps) > 0): ?>
        <div class="detail-section">
            <h2>Previous Applications</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Name Used</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prev_apps as $app): ?>
                        <tr>
                            <td><?php echo $app['application_date'] ? date('M d, Y', strtotime($app['application_date'])) : 'N/A'; ?></td>
                            <td><?php echo htmlspecialchars($app['name_used']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($housing && $housing['in_subsidized_housing'] === 'yes'): ?>
        <div class="detail-section">
            <h2>Subsidized Housing</h2>
            <table class="info-table">
                <tr>
                    <th>In Subsidized Housing:</th>
                    <td>Yes</td>
                </tr>
                <?php if ($housing['rent_amount']): ?>
                <tr>
                    <th>Amount of Rent:</th>
                    <td>$<?php echo number_format($housing['rent_amount'], 2); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($income): ?>
        <div class="detail-section">
            <h2>Household Income</h2>
            <table class="info-table">
                <tr>
                    <th>Child Support:</th>
                    <td>$<?php echo number_format($income['child_support'], 2); ?></td>
                </tr>
                <tr>
                    <th>Pension:</th>
                    <td>$<?php echo number_format($income['pension'], 2); ?></td>
                </tr>
                <tr>
                    <th>Wages:</th>
                    <td>$<?php echo number_format($income['wages'], 2); ?></td>
                </tr>
                <tr>
                    <th>SS/SSD/SSI:</th>
                    <td>$<?php echo number_format($income['ss_ssd_ssi'], 2); ?></td>
                </tr>
                <tr>
                    <th>Unemployment:</th>
                    <td>$<?php echo number_format($income['unemployment'], 2); ?></td>
                </tr>
                <tr>
                    <th>Food Stamps:</th>
                    <td>$<?php echo number_format($income['food_stamps'], 2); ?></td>
                </tr>
                <tr>
                    <th>Other:</th>
                    <td>$<?php echo number_format($income['other'], 2); ?></td>
                </tr>
                <?php if ($income['other_description']): ?>
                <tr>
                    <th>Other Description:</th>
                    <td><?php echo htmlspecialchars($income['other_description']); ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <th>Total Household Income:</th>
                    <td><strong>$<?php echo number_format($income['total_household_income'], 2); ?></strong></td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <div class="detail-section">
            <h2>Visit History</h2>
            <?php if (count($visits) > 0): ?>
                <div style="margin-bottom: 1rem;">
                    <strong>Visit Summary:</strong>
                    <?php 
                    $visit_summary = [];
                    if (!empty($visit_counts['food'])) $visit_summary[] = "Food: " . $visit_counts['food'];
                    
                    // Money visits with limit counter
                    $money_limit = intval(getSetting('money_distribution_limit', 3));
                    if (!empty($visit_counts['money'])) {
                        // Get household money count
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM visits v 
                                           INNER JOIN household_members hm1 ON v.customer_id = hm1.customer_id
                                           INNER JOIN household_members hm2 ON hm1.name = hm2.name
                                           WHERE hm2.customer_id = ? AND v.visit_type = 'money'");
                        $stmt->execute([$customer_id]);
                        $household_money = $stmt->fetch()['count'];
                        $visit_summary[] = "Money: {$household_money}/{$money_limit}";
                    }
                    
                    if (!empty($visit_counts['voucher'])) $visit_summary[] = "Vouchers: " . $visit_counts['voucher'];
                    echo !empty($visit_summary) ? implode(" | ", $visit_summary) : "No visits";
                    ?>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td><?php echo date('M d, Y \a\t g:i A', strtotime($visit['visit_date'])); ?></td>
                                <td><?php echo ucfirst($visit['visit_type'] ?? 'food'); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($visit['notes'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="no-data">No visits recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

