<?php
include '../database/database.php';
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: Login.php');
    date_default_timezone_set('Asia/Manila'); 
    exit;
}

// Handle theme setting via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_theme') {
    $theme = $_POST['theme'] ?? 'light';
    $_SESSION['theme'] = $theme;
    echo json_encode(['status' => 'success', 'theme' => $theme]);
    exit;
}

// Set timezone
date_default_timezone_set('Asia/Manila');

// Get start and end of the current week (Monday to Sunday)
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$end_of_week = date('Y-m-d', strtotime('sunday this week'));

// Fetch Total Sales for the current week
try {
    $sales_stmt = $conn->prepare("
        SELECT SUM(Total) as total_sales 
        FROM CustomerOrders 
        WHERE OrderDate BETWEEN :start_date AND :end_date
    ");
    $sales_stmt->execute([
        ':start_date' => $start_of_week,
        ':end_date' => $end_of_week
    ]);
    $total_sales = $sales_stmt->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0.00;
} catch (PDOException $e) {
    $total_sales = 'Error: ' . $e->getMessage();
}

// Fetch Total Orders
try {
    $orders_stmt = $conn->prepare("SELECT COUNT(*) as total_orders FROM CustomerOrders");
    $orders_stmt->execute();
    $total_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC)['total_orders'] ?? 0;
} catch (PDOException $e) {
    $total_orders = 'Error: ' . $e->getMessage();
}

// Fetch Total Customers
try {
    $customers_stmt = $conn->prepare("SELECT COUNT(DISTINCT CustomerName) as total_customers FROM CustomerOrders");
    $customers_stmt->execute();
    $total_customers = $customers_stmt->fetch(PDO::FETCH_ASSOC)['total_customers'] ?? 0;
} catch (PDOException $e) {
    $total_customers = 'Error: ' . $e->getMessage();
}

// Fetch Recent Orders (last 5)
try {
    $recent_orders_stmt = $conn->prepare("
        SELECT 
            co.OrderID,
            co.OrderDate,
            coi.ProductName,
            co.CustomerName,
            co.Total,
            co.Status,
            co.PaymentTerms
        FROM CustomerOrders co
        LEFT JOIN CustomerOrderItems coi ON co.OrderID = coi.OrderID
        ORDER BY co.OrderDate DESC
        LIMIT 5
    ");
    $recent_orders_stmt->execute();
    $recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_orders = ['error' => 'Error: ' . $e->getMessage()];
}

// Fetch Quantity on Hand (from Inventory)
try {
    $quantity_on_hand_stmt = $conn->prepare("SELECT SUM(stock) as total_stock FROM products WHERE status = 'active'");
    $quantity_on_hand_stmt->execute();
    $quantity_on_hand = $quantity_on_hand_stmt->fetch(PDO::FETCH_ASSOC)['total_stock'] ?? 0;
} catch (PDOException $e) {
    $quantity_on_hand = 'Error: ' . $e->getMessage();
}

// Fetch Quantity to be Received (from SupplierOrder, all orders)
try {
    $quantity_to_receive_stmt = $conn->prepare("
        SELECT SUM(soi.Quantity) as total_to_receive 
        FROM SupplierOrders so
        LEFT JOIN SupplierOrderItems soi ON so.OrderID = soi.OrderID
    ");
    $quantity_to_receive_stmt->execute();
    $quantity_to_receive = $quantity_to_receive_stmt->fetch(PDO::FETCH_ASSOC)['total_to_receive'] ?? 0;
} catch (PDOException $e) {
    $quantity_to_receive = 'Error: ' . $e->getMessage();
}

// Fetch "To be Shipped" count from CustomerOrders
try {
    $to_be_shipped_stmt = $conn->prepare("
        SELECT COUNT(*) as to_be_shipped 
        FROM CustomerOrders 
        WHERE Status = 'Shipped'
    ");
    $to_be_shipped_stmt->execute();
    $to_be_shipped = $to_be_shipped_stmt->fetch(PDO::FETCH_ASSOC)['to_be_shipped'] ?? 0;
} catch (PDOException $e) {
    $to_be_shipped = 'Error: ' . $e->getMessage();
}

// Fetch "To be Delivered" count from CustomerOrders
try {
    $to_be_delivered_stmt = $conn->prepare("
        SELECT COUNT(*) as to_be_delivered 
        FROM CustomerOrders 
        WHERE Status = 'Delivered'
    ");
    $to_be_delivered_stmt->execute();
    $to_be_delivered = $to_be_delivered_stmt->fetch(PDO::FETCH_ASSOC)['to_be_delivered'] ?? 0;
} catch (PDOException $e) {
    $to_be_delivered = 'Error: ' . $e->getMessage();
}

// Fetch Daily Sales for the current week
$daily_sales = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("$start_of_week +$i days"));
    try {
        $daily_stmt = $conn->prepare("
            SELECT SUM(Total) as daily_total 
            FROM CustomerOrders 
            WHERE DATE(OrderDate) = :date
        ");
        $daily_stmt->execute([':date' => $date]);
        $daily_sales[$date] = $daily_stmt->fetch(PDO::FETCH_ASSOC)['daily_total'] ?? 0.00;
    } catch (PDOException $e) {
        $daily_sales[$date] = 0.00;
    }
}

// Fetch Weekly Sales for the current month (4 weeks)
$weekly_sales = [];
$start_of_month = date('Y-m-01');
$end_of_month = date('Y-m-t');
for ($week_start = strtotime($start_of_month); $week_start <= strtotime($end_of_month); $week_start += 7 * 86400) {
    $week_end = date('Y-m-d', $week_start + 6 * 86400);
    $week_start_date = date('Y-m-d', $week_start);
    try {
        $weekly_stmt = $conn->prepare("
            SELECT SUM(Total) as weekly_total 
            FROM CustomerOrders 
            WHERE OrderDate BETWEEN :start_date AND :end_date
        ");
        $weekly_stmt->execute([
            ':start_date' => $week_start_date,
            ':end_date' => $week_end
        ]);
        $week_label = "Week of " . date('M d', $week_start);
        $weekly_sales[$week_label] = $weekly_stmt->fetch(PDO::FETCH_ASSOC)['weekly_total'] ?? 0.00;
    } catch (PDOException $e) {
        $weekly_sales[$week_label] = 0.00;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/dashboard.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .graph-container {
            width: 100%;
            height: 400px;
            margin: 20px 0 50px 0;
        }
        #toggleGraphBtn {
            margin: 10px 0;
        }
        .recent-orders, .inventory-summary {
            margin-top: 40px;
        }

        /* Base styles */
        body {
            background-color: #f4f4f4;
            color: #333;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Light Mode */
        body.light-mode {
            background-color: #f4f4f4;
            color: #333;
        }
        .light-mode .left-sidebar {
            background-color: #343F79;
        }
        .light-mode .left-sidebar .menu li a,
        .light-mode .left-sidebar .menu li span,
        .light-mode .left-sidebar .submenu li a {
            color: white;
            text-decoration: none;
        }
        .light-mode .left-sidebar .menu li:hover,
        .light-mode .left-sidebar .menu li.dropdown.active {
            background-color: #4a568a;
        }
        .light-mode .card {
            background-color: #fff;
            color: #333;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .light-mode .main-content {
            background-color: #f4f4f4;
        }

        /* Dark Mode */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #e0e0e0;
        }
        .dark-mode .left-sidebar {
            background-color: #2c2c2c;
        }
        .dark-mode .left-sidebar .menu li a,
        .dark-mode .left-sidebar .menu li span,
        .dark-mode .left-sidebar .submenu li a {
            color: #e0e0e0;
            text-decoration: none;
        }
        .dark-mode .left-sidebar .menu li:hover,
        .dark-mode .left-sidebar .menu li.dropdown.active {
            background-color: #3a3a3a;
        }
        .dark-mode .main-content {
            background-color: #252525;
        }
        .dark-mode .card {
            background-color: #3a3a3a;
            color: #e0e0e0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.5);
        }
        .dark-mode .right-sidebar {
            background-color: #2c2c2c;
            color: #e0e0e0;
        }
        .dark-mode .right-card {
            color: #e0e0e0;
        }
        .dark-mode table {
            color: #e0e0e0;
            background-color: #333;
        }
        .dark-mode table thead th {
            background-color: #444;
            color: #e0e0e0;
        }
        .dark-mode table tbody tr {
            background-color: #2c2c2c;
        }
        .dark-mode table tbody tr:hover {
            background-color: #3a3a3a;
        }
        .dark-mode h1, .dark-mode h3, .dark-mode p {
            color: #e0e0e0;
        }
        .dark-mode input[type="text"] {
            background-color: #444;
            color: #e0e0e0;
            border: 1px solid #555;
        }
        .dark-mode .btn-primary {
            background-color: #1e90ff;
            border-color: #1e90ff;
        }

        /* Sidebar-specific styles */
        .left-sidebar {
            transition: background-color 0.3s;
        }
        .left-sidebar .menu {
            list-style: none;
            padding: 0;
        }
        .left-sidebar .menu li {
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .left-sidebar .submenu {
            display: none;
            list-style: none;
            padding-left: 20px;
        }
        .left-sidebar .menu li.dropdown.active .submenu {
            display: block;
        }

        /* Settings menu */
        #logoutMenu {
            z-index: 1000;
            background-color: #ffffff;
            border: 1px solid #999;
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
            min-width: 120px;
            position: absolute;
        }
        .dark-mode #logoutMenu {
            background-color: #333;
            border: 1px solid #555;
        }
        #logoutMenu a {
            color: #333;
            padding: 10px 20px;
            text-decoration: none;
            display: block;
        }
        .dark-mode #logoutMenu a {
            color: #e0e0e0;
        }
        #logoutMenu a:hover {
            background-color: #f0f0f0;
            color: #000;
        }
        .dark-mode #logoutMenu a:hover {
            background-color: #444;
            color: #fff;
        }
    </style>
</head>
<body class="<?php echo isset($_SESSION['theme']) ? $_SESSION['theme'] . '-mode' : 'light-mode'; ?>">
    <div class="left-sidebar">
        <img src="../images/Logo.jpg" alt="Le Parisien" class="logo">
        <ul class="menu">
            <li><i class="fa fa-home"></i><span><a href="dashboard.php"> Home</a></span></li>
            <li><i class="fa fa-box"></i><span><a href="Inventory.php"> Inventory</a></span></li>
            <li class="dropdown">
                <i class="fa fa-store"></i><span> Retailer</span><i class="fa fa-chevron-down toggle-btn"></i>
                <ul class="submenu">
                    <li><a href="supplier.php">Supplier</a></li>
                    <li><a href="SupplierOrder.php">Supplier Order</a></li>
                    <li><a href="Deliverytable.php">Delivery</a></li>
                </ul>
            </li>
            <li class="dropdown">
                <i class="fa fa-chart-line"></i><span> Sales</span><i class="fa fa-chevron-down toggle-btn"></i>
                <ul class="submenu">
                    <li><a href="Customers.php">Customers</a></li>
                    <li><a href="Invoice.php">Invoice</a></li>
                    <li><a href="CustomerOrder.php">Customer Order</a></li>
                </ul>
            </li>
            <li class="dropdown">
                <i class="fa fa-store"></i><span> Admin</span><i class="fa fa-chevron-down toggle-btn"></i>
                <ul class="submenu">
                    <li><a href="UserManagement.php">User Management</a></li>
                    <li><a href="Employees.php">Employees</a></li>
                    <li><a href="AuditLogs.php">Audit Logs</a></li>
                </ul>
            </li>
            <li>
                <a href="Reports.php">
                    <i class="fas fa-file-invoice-dollar"></i><span> Reports</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i><span> Log out</span>
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <header>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                    <p><?php echo date('d F Y'); ?></p>
                </div>
                <div class="right-sidebar-toggle" style="display: flex; align-items: center;">
                    <!-- Removed notification icon: <i class="fa fa-bell" style="margin-right: 20px; font-size: 30px; position: relative; top: -20px;"></i> -->
                    <div style="position: relative; display: inline-block;">
                        <i class="fa fa-cog" id="settingsIcon" style="margin-right: 20px; font-size: 30px; position: relative; top: -20px; cursor: pointer;"></i>
                        <div id="logoutMenu" style="display: none; position: absolute; top: 20px; right: 0;">
                            <a href="#" id="lightModeBtn">Light Mode</a>
                            <a href="#" id="darkModeBtn">Dark Mode</a>
                        </div>
                    </div>
                    <div>
                        <i class="fa fa-user-circle" style="font-size: 40px; cursor: pointer; position: relative; top: -20px;" onclick="toggleRightSidebar()"></i>
                    </div>
                </div>
            </div>
        </header>

        <div class="cards">
            <div class="card">Total Sales (This Week) <br> <strong>₱<?php echo is_numeric($total_sales) ? number_format($total_sales, 2) : $total_sales; ?></strong></div>
            <div class="card">Total Order <br> <strong><?php echo htmlspecialchars($total_orders); ?></strong></div>
            <div class="card">Total Customer <br> <strong><?php echo htmlspecialchars($total_customers); ?></strong></div>
        </div>

        <div class="graph-container">
            <button id="toggleGraphBtn" class="btn btn-primary">Switch to Weekly</button>
            <canvas id="salesChart"></canvas>
        </div>

        <div class="recent-orders">
            <h3>Recent Orders</h3>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($recent_orders['error'])): ?>
                        <tr><td colspan="7" class="text-center text-danger"><?php echo htmlspecialchars($recent_orders['error']); ?></td></tr>
                    <?php elseif (!empty($recent_orders)): ?>
                        <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['OrderID']); ?></td>
                                <td><?php echo htmlspecialchars(date('d M Y', strtotime($order['OrderDate']))); ?></td>
                                <td><?php echo htmlspecialchars($order['ProductName'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['CustomerName']); ?></td>
                                <td>₱<?php echo number_format($order['Total'], 2); ?></td>
                                <td><?php echo htmlspecialchars($order['Status']); ?></td>
                                <td><?php echo htmlspecialchars($order['PaymentTerms'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center text-muted">No order yet. Add new order.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="inventory-summary">
            <h3>Inventory Summary</h3>
            <p>Quantity on hand: <strong><?php echo htmlspecialchars($quantity_on_hand); ?></strong></p>
            <p>Quantity to be received: <strong><?php echo htmlspecialchars($quantity_to_receive); ?></strong></p>
        </div>
    </div>

    <div class="right-sidebar">
        <div style="display: flex; align-items: center; margin-bottom: 20px; margin-right: 60px;">
            <img src="../images/PFP.jpg" alt="User Image" style="width: 60px; height: 57px; border-radius: 50%; margin-right: 10px;">
            <div>
                <span style="font-size: 20px;">
                    <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
                </span>
                <br>
                <span style="font-size: 10px;">
                    <?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : 'Admin'; ?>
                </span>
            </div>
        </div>
        <div style="display: flex; align-items: center; margin-bottom: 20px; flex-direction: column;">
            <input type="text" placeholder="Search" style="padding: 10px 20px; background-color: white; color: black; border: none; border-radius: 5px; width: 230px; height: 35px;">
            <div style="margin-top: 20px;">
                <div class="right-card" style="font-size: 20px;">To be Shipped <br> <strong style="font-size: 30px;"><?php echo htmlspecialchars($to_be_shipped); ?></strong></div>
                <div class="right-card" style="font-size: 20px;">To be Delivered <br> <strong style="font-size: 30px;"><?php echo htmlspecialchars($to_be_delivered); ?></strong></div>
            </div>
        </div>
    </div>

    <script>
        function toggleRightSidebar() {
            const rightSidebar = document.querySelector('.right-sidebar');
            const mainContent = document.querySelector('.main-content');
            const isOpen = rightSidebar.style.display === 'flex';
            rightSidebar.style.display = isOpen ? 'none' : 'flex';
            mainContent.classList.toggle('right-sidebar-open', !isOpen);
            rightSidebar.style.overflowY = 'auto';
        }

        // Sidebar dropdown toggle
        document.querySelectorAll('.dropdown').forEach(item => {
            item.addEventListener('click', function (e) {
                e.stopPropagation(); // Prevent event bubbling
                this.classList.toggle('active');
            });
        });

        const settingsIcon = document.getElementById('settingsIcon');
        const logoutMenu = document.getElementById('logoutMenu');
        const lightModeBtn = document.getElementById('lightModeBtn');
        const darkModeBtn = document.getElementById('darkModeBtn');

        settingsIcon.addEventListener('click', () => {
            logoutMenu.style.display = logoutMenu.style.display === 'none' ? 'block' : 'none';
        });

        document.addEventListener('click', (event) => {
            if (!settingsIcon.contains(event.target) && !logoutMenu.contains(event.target)) {
                logoutMenu.style.display = 'none';
            }
        });

        // Theme toggle logic with session persistence
        function setTheme(theme) {
            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=set_theme&theme=${theme}`
            })
            .then(response => response.json())
            .then(data => {
                console.log('Theme set response:', data); // Debug log
                if (data.status === 'success') {
                    document.body.classList.remove('light-mode', 'dark-mode');
                    document.body.classList.add(`${data.theme}-mode`);
                }
            })
            .catch(error => console.error('Error setting theme:', error));
        }

        lightModeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            setTheme('light');
            logoutMenu.style.display = 'none';
        });

        darkModeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            setTheme('dark');
            logoutMenu.style.display = 'none';
        });

        // Apply saved theme on page load
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = '<?php echo isset($_SESSION['theme']) ? $_SESSION['theme'] : 'light'; ?>';
            console.log('Applying saved theme:', savedTheme); // Debug log
            document.body.classList.remove('light-mode', 'dark-mode');
            document.body.classList.add(`${savedTheme}-mode`);
        });

        // Chart.js setup
        const dailySalesData = <?php echo json_encode($daily_sales); ?>;
        const weeklySalesData = <?php echo json_encode($weekly_sales); ?>;

        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Object.keys(dailySalesData).map(date => new Date(date).toLocaleDateString('en-US', { weekday: 'short' })),
                datasets: [{
                    label: 'Daily Sales',
                    data: Object.values(dailySalesData),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Sales (₱)' } },
                    x: { title: { display: true, text: 'Day' } }
                }
            }
        });

        const toggleGraphBtn = document.getElementById('toggleGraphBtn');
        let showingDaily = true;

        toggleGraphBtn.addEventListener('click', () => {
            if (showingDaily) {
                salesChart.data.labels = Object.keys(weeklySalesData);
                salesChart.data.datasets[0].label = 'Weekly Sales';
                salesChart.data.datasets[0].data = Object.values(weeklySalesData);
                salesChart.options.scales.x.title.text = 'Week';
                toggleGraphBtn.textContent = 'Switch to Daily';
            } else {
                salesChart.data.labels = Object.keys(dailySalesData).map(date => new Date(date).toLocaleDateString('en-US', { weekday: 'short' }));
                salesChart.data.datasets[0].label = 'Daily Sales';
                salesChart.data.datasets[0].data = Object.values(dailySalesData);
                salesChart.options.scales.x.title.text = 'Day';
                toggleGraphBtn.textContent = 'Switch to Weekly';
            }
            showingDaily = !showingDaily;
            salesChart.update();
        });
    </script>
</body>
</html>