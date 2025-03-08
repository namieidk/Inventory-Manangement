<?php
require_once '../database/database.php';
function logAction($conn, $userId, $action, $description) {
    try {
        $stmt = $conn->prepare("CALL LogAction(?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $description
        ]);
    } catch (PDOException $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}

// Unified fetch function for audit logs using stored procedure
function fetchAuditLogs($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        error_log("Calling FetchAuditLogs with search term: '$searchTerm', orderBy: '$orderBy', filterBy: '$filterBy'");
        $stmt = $conn->prepare("CALL FetchAuditLogs(:search, :order_by, :filter_by)");
        $stmt->execute([
            ':search' => $searchTerm,
            ':order_by' => $orderBy,
            ':filter_by' => $filterBy
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fetched " . count($results) . " audit log entries");
        return $results;
    } catch (PDOException $e) {
        error_log("Error fetching audit logs: " . $e->getMessage());
        return [];
    }
}

// Handle AJAX search request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $logs = fetchAuditLogs($conn, $searchTerm);
    header('Content-Type: application/json');
    echo json_encode($logs);
    exit;
}

// Initial page load
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$logs = fetchAuditLogs($conn, $searchTerm);

// Log the action of accessing the Audit Logs page
$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null; // Adjusted to match session variable used elsewhere
logAction($conn, $userId, "Accessed Audit Logs", "User viewed the audit logs page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/AuditLogs.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="left-sidebar">
    <img src="../images/Logo.jpg" alt="Le Parisien" class="logo">
    <ul class="menu">
        <li><i class="fa fa-home"></i><span><a href="dashboard.php" style="color: white; text-decoration: none;"> Home</a></span></li>
        <li><i class="fa fa-box"></i><span><a href="Inventory.php" style="color: white; text-decoration: none;"> Inventory</a></span></li>
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
        <li class="dropdown active">
            <i class="fa fa-store"></i><span> Admin</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="UserManagement.php" style="color: white; text-decoration: none;">User Management </a></li>
                <li><a href="AuditLogs.php" style="color: white; text-decoration: none;">Audit Logs</a></li>
            </ul>
        </li>
        <li>
            <a href="Reports.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-file-invoice-dollar"></i><span> Reports</span>
            </a>
        </li>
        <li>
            <a href="logout.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-sign-out-alt"></i><span> Logout</span>
            </a>
        </li>
    </ul>
</div>

<div class="main-content">
    <h1>Audit Logs</h1>
    <div class="d-flex justify-content-between mb-3">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
    </div>

    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Action</th>
                <th>Description</th>
                <th>Time and Date</th>
            </tr>
        </thead>
        <tbody id="auditLogTableBody">
            <?php if (empty($logs)): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No audit logs available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['usersId'] ?? ''); ?></td> <!-- Changed to usersId -->
                        <td><?php echo htmlspecialchars($log['action'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($log['timestamp'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown functionality for sidebar
    var dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function(dropdown) {
        var toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', function() {
            dropdown.classList.toggle('active');
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;

    function updateTable() {
        const searchTerm = searchInput.value.trim();

        fetch('AuditLogs.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=search&search=${encodeURIComponent(searchTerm)}`
        })
        .then(response => response.json())
        .then(logs => {
            console.log('Logs received:', logs);
            const tbody = document.getElementById('auditLogTableBody');
            tbody.innerHTML = '';

            if (logs.length > 0) {
                logs.forEach(log => {
                    const row = `
                        <tr>
                            <td>${log.usersId || ''}</td> <!-- Changed to usersId -->
                            <td>${log.action || ''}</td>
                            <td>${log.description || ''}</td>
                            <td>${log.timestamp || ''}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No audit logs available.</td></tr>';
            }
        })
        .catch(error => {
            console.error('AJAX error:', error);
            document.getElementById('auditLogTableBody').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading audit logs</td></tr>';
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300); // 300ms debounce
    });

    // Initial table load
    updateTable();
});
</script>
</body>
</html>