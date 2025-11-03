<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>NexusDB</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php"><?php echo htmlspecialchars(getSetting('organization_name', 'NexusDB')); ?></a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="signup.php">New Signup</a></li>
                <li><a href="customers.php"><?php echo htmlspecialchars(getCustomerTermPlural('Customers')); ?></a></li>
                <li class="nav-dropdown">
                    <a href="#" class="nav-dropdown-toggle">Visits <span class="dropdown-arrow">▼</span></a>
                    <ul class="nav-dropdown-menu">
                        <li><a href="visits_food.php">Food Visit</a></li>
                        <li><a href="visits_money.php">Money Visit</a></li>
                        <li><a href="visits_voucher.php">Voucher Visit</a></li>
                    </ul>
                </li>
                <li><a href="vouchers.php">Vouchers</a></li>
                <li><a href="voucher_redemption.php">Redeem</a></li>
                <li><a href="reports.php">Reports</a></li>
                <li><a href="settings.php">Settings</a></li>
            </ul>
        </div>
    </nav>
    <main class="main-content">

