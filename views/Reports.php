<?php
include '../database/database.php';

// Handle sales report fetch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            throw new Exception('Invalid JSON input');
        }

        $startDate = $input['startDate'] ?? null;
        $endDate = $input['endDate'] ?? null;

        if (!$startDate || !$endDate) {
            throw new Exception('Invalid date range');
        }

        $sql = "
            SELECT 
                coi.ProductName AS Product,
                SUM(coi.Quantity) AS Quantity,
                SUM(coi.Amount) AS Amount
            FROM CustomerOrders co
            JOIN CustomerOrderItems coi ON co.OrderID = coi.OrderID
            WHERE co.OrderDate BETWEEN :startDate AND :endDate
            GROUP BY coi.ProductName
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Clear buffer and send JSON response
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    } catch (Exception $e) {
        // Clear buffer and send error as JSON
        ob_end_clean();
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard - Sales Reports</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Reports.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <style>
        .date-inputs {
            display: flex;
            flex-direction: row; /* Side-by-side inputs */
            gap: 15px; /* Space between inputs */
            margin-bottom: 20px;
        }
        .date-inputs .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .date-inputs input {
            width: 100%;
        }
    </style>
</head>
<body>
<div class="left-sidebar">
    <img src="../images/Logo.jpg" alt="Le Parisien" class="logo">
    <ul class="menu">
        <li><i class="fa fa-home"></i><span><a href="dashboard.php" style="color: white; text-decoration: none;"> Home</a></span></li>
        <li><i class="fa fa-box"></i><span><a href="Inventory.php" style="color: white; text-decoration: none;"> Inventory</a></span></li>
        <li><i class="fa fa-credit-card"></i><span><a href="Payment.php" style="color: white; text-decoration: none;"> Payment</a></span></li>
        <li class="dropdown">
            <i class="fa fa-store"></i><span> Retailer</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="supplier.php" style="color: white; text-decoration: none;">Supplier</a></li>
                <li><a href="SupplierOrder.php" style="color: white; text-decoration: none;">Supplier Order</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <i class="fa fa-chart-line"></i><span> Sales</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="Customers.php" style="color: white; text-decoration: none;">Customers</a></li>
                <li><a href="Invoice.php" style="color: white; text-decoration: none;">Invoice</a></li>
                <li><a href="CustomerOrder.php" style="color: white; text-decoration: none;">Customer Order</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <i class="fa fa-store"></i><span> Admin</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="UserManagement.php" style="color: white; text-decoration: none;">User Management </a></li>
                <li><a href="Employees.php" style="color: white; text-decoration: none;">Employees</a></li>
                <li><a href="AuditLogs.php" style="color: white; text-decoration: none;">Audit Logs</a></li>
            </ul>
        </li>
        <li>
            <a href="Reports.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-file-invoice-dollar"></i><span> Reports</span>
            </a>
        </li>
    </ul>
</div>

<div class="main-content">
    <h1>Sales Reports</h1>

    <div class="date-inputs">
        <div class="form-group">
            <label for="dateRangeStart">Start Date</label>
            <input type="text" class="form-control" id="dateRangeStart" name="dateRangeStart" placeholder="DD/MM/YYYY">
        </div>
        <div class="form-group">
            <label for="dateRangeEnd">End Date</label>
            <input type="text" class="form-control" id="dateRangeEnd" name="dateRangeEnd" placeholder="DD/MM/YYYY">
        </div>
    </div>

    <table class="table table-striped table-hover" id="reportTable">
        <thead id="reportTableHead">
            <tr>
                <th>Product</th>
                <th>Quantity</th>
                <th>Revenue</th>
                <th>Cost of Goods</th>
                <th>Profit</th>
            </tr>
        </thead>
        <tbody id="reportTableBody">
            <tr>
                <td colspan="5" class="text-center text-muted">Sales report will load automatically. Adjust date range if needed.</td>
            </tr>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function(dropdown) {
        var toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', function() {
            dropdown.classList.toggle('active');
        });
    });

    const dateRangeStartInput = document.getElementById('dateRangeStart');
    const dateRangeEndInput = document.getElementById('dateRangeEnd');
    const tbody = document.getElementById('reportTableBody');
    let searchTimeout;

    // Function to fetch sales report
    function fetchSalesReport() {
        let start = dateRangeStartInput.value;
        let end = dateRangeEndInput.value;

        if (!start || !end) {
            const endDate = new Date();
            const startDate = new Date();
            startDate.setDate(endDate.getDate() - 30);
            start = formatDate(startDate);
            end = formatDate(endDate);
            dateRangeStartInput.value = start;
            dateRangeEndInput.value = end;
        }

        const startDb = parseDate(start);
        const endDb = parseDate(end);

        if (!startDb || !endDb) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Invalid date range provided.</td></tr>';
            return;
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ startDate: startDb, endDate: endDb })
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No sales data available for this range.</td></tr>';
                return;
            }

            if (data.error) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Server error: ${data.error}</td></tr>`;
                return;
            }

            const productData = {};
            data.forEach(item => {
                if (!productData[item.Product]) {
                    productData[item.Product] = { qty: 0, revenue: 0, cost: 0 };
                }
                productData[item.Product].qty += parseInt(item.Quantity);
                productData[item.Product].revenue += parseFloat(item.Amount);
                productData[item.Product].cost += getCostOfGoods(item.Product, item.Quantity);
            });

            tbody.innerHTML = '';
            let totalQty = 0, totalRevenue = 0, totalCost = 0;
            for (const [product, info] of Object.entries(productData)) {
                const profit = info.revenue - info.cost;
                tbody.innerHTML += `
                    <tr>
                        <td>${product}</td>
                        <td>${info.qty}</td>
                        <td>₱${info.revenue.toFixed(2)}</td>
                        <td>₱${info.cost.toFixed(2)}</td>
                        <td>₱${profit.toFixed(2)}</td>
                    </tr>
                `;
                totalQty += info.qty;
                totalRevenue += info.revenue;
                totalCost += info.cost;
            }

            tbody.innerHTML += `
                <tr>
                    <td><strong>Total</strong></td>
                    <td><strong>${totalQty}</strong></td>
                    <td><strong>₱${totalRevenue.toFixed(2)}</strong></td>
                    <td><strong>₱${totalCost.toFixed(2)}</strong></td>
                    <td><strong>₱${(totalRevenue - totalCost).toFixed(2)}</strong></td>
                </tr>
            `;
        })
        .catch(error => {
            console.error('Fetch Error:', error.message);
            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Error loading sales data: ${error.message}</td></tr>`;
        });
    }

    // Debounced fetch on date input change
    dateRangeStartInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(fetchSalesReport, 500); // 500ms debounce
    });

    dateRangeEndInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(fetchSalesReport, 500); // 500ms debounce
    });

    // Initial fetch on page load
    fetchSalesReport();

    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
    }

    function parseDate(dateStr) {
        try {
            const [day, month, year] = dateStr.split('/');
            if (!day || !month || !year) throw new Error('Invalid date format');
            return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
        } catch (e) {
            console.error('Date Parse Error:', e.message, dateStr);
            return null;
        }
    }

    function getCostOfGoods(product, quantity) {
        const costPerUnit = 50.00; // Replace with real logic if needed
        return quantity * costPerUnit;
    }
});
</script>
</body>
</html>