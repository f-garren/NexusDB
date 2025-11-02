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
                <a href="index.php">NexusDB</a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Dashboard</a></li>
                <li><a href="signup.php">New Signup</a></li>
                <li><a href="customers.php">Customers</a></li>
                <li><a href="visits.php">Visits</a></li>
                <li><a href="reports.php">Reports</a></li>
            </ul>
        </div>
    </nav>
    <main class="main-content">

