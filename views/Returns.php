<?php
session_start();
include '../database/database.php'; // Assuming this contains your PDO connection ($conn)

// Define $filterBy and $orderBy globally with default empty values
$filterBy = isset($_GET['filterBy']) ? $_GET['filterBy'] : '';
$orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : '';

// Function to fetch returns from the database
function fetchReturns($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        $sql = "SELECT 
            r.return_id AS ReturnID,
            r.customer_name AS CustomerName,
            r.order_id AS OrderID,
            r.return_date AS ReturnDate,
            r.total_refunded AS TotalRefunded,
            r.status AS Status,
            COALESCE(SUM(ri.quantity), 0) AS TotalQuantity
        FROM Returns r
        LEFT JOIN ReturnItems ri ON r.return_id = ri.return_id
        WHERE 1=1";
        
        $params = [];
        $whereClause = '';
        $orderClause = " ORDER BY r.return_date DESC"; // Default to newest

        // Search functionality
        if (!empty($searchTerm)) {
            $whereClause .= " AND (r.return_id LIKE :search 
                            OR r.customer_name LIKE :search 
                            OR r.order_id LIKE :search 
                            OR r.status LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Filter by total_refunded
        switch ($filterBy) {
            case 'price-below-1000':
                $whereClause .= " AND r.total_refunded < 1000";
                break;
            case 'price-1000-5000':
                $whereClause .= " AND r.total_refunded BETWEEN 1000 AND 5000";
                break;
            case 'price-5000-10000':
                $whereClause .= " AND r.total_refunded BETWEEN 5000 AND 10000";
                break;
            case 'price-above-10000':
                $whereClause .= " AND r.total_refunded > 10000";
                break;
        }

        // Order by logic
        switch ($orderBy) {
            case 'name-asc':
                $orderClause = " ORDER BY r.customer_name ASC";
                break;
            case 'name-desc':
                $orderClause = " ORDER BY r.customer_name DESC";
                break;
            case 'price-asc':
                $orderClause = " ORDER BY r.total_refunded ASC";
                break;
            case 'price-desc':
                $orderClause = " ORDER BY r.total_refunded DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY r.return_date DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY r.return_date ASC";
                break;
        }

        $sql .= $whereClause . " 
            GROUP BY r.return_id, r.customer_name, r.order_id, r.return_date, r.total_refunded, r.status
        " . $orderClause;

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Initial fetch of returns
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$returns = fetchReturns($conn, $searchTerm, $orderBy, $filterBy);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns Table</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/CustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .left-sidebar { position: fixed; top: 0; left: -250px; width: 250px; height: 100%; background-color: #343F79; transition: left 0.3s ease; z-index: 1000; }
        .left-sidebar.active { left: 0; }
        .main-content { width: 100%; max-width: 100%; margin-left: auto; margin-right: 0; padding: 20px; box-sizing: border-box; }
        .menu-btn { font-size: 24px; background: none; border: none; cursor: pointer; margin-right: 10px; }
        .header-container { display: flex; align-items: center; margin-bottom: 20px; }
        .search-container { position: relative; width: 300px; margin-left: 45px; margin-right: 700px; }
        .search-container .form-control { padding-left: 35px; }
        .search-container .fa-search { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #6c757d; }
        .order-link { cursor: pointer; color: #007bff; text-decoration: underline; }
        .order-link:hover { color: #0056b3; }
        .controls-container { display: flex; align-items: center; justify-content: flex-start; margin-bottom: 15px; width: 100%; }
        .btn-outline-secondary { margin-right: 15px; min-width: 150px; }
    </style>
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
                <li><a href="Deliverytable.php">Delivery</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <i class="fa fa-chart-line"></i><span> Sales</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="Customers.php" style="color: white; text-decoration: none;">Customers</a></li>
                <li><a href="CustomerOrder.php" style="color: white; text-decoration: none;">Customer Order</a></li>
                <li><a href="Invoice.php" style="color: white; text-decoration: none;">Invoice</a></li>
                <li><a href="Returns.php" style="color: white; text-decoration: none;">Returns</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <i class="fa fa-store"></i><span> Admin</span><i class="fa fa-chevron-down toggle-btn"></i>
            <ul class="submenu">
                <li><a href="UserManagement.php" style="color: white; text-decoration: none;">User Management </a></li>
                <li><a href="AuditLogs.php" style="color: white; text-decoration: none;">Audit Logs</a></li>
            </ul>
        </li>
        <li class="dropdown">
    <i class="fas fa-file-invoice-dollar"></i><span> Reports</span><i class="fa fa-chevron-down toggle-btn"></i>
    <ul class="submenu">
        <li><a href="Reports.php" style="color: white; text-decoration: none;">Sales</a></li>
        <li><a href="InventoryReports.php" style="color: white; text-decoration: none;">Inventory</a></li>
    </ul>
</li>
        <li>
            <a href="logout.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-sign-out-alt"></i><span> Log out</span>
            </a>
        </li>
    </ul>
</div>


<div class="main-content">
    <div class="header-container">
        <button class="menu-btn" id="menuToggleBtn"><i class="fas fa-bars"></i></button>
        <h1>Returns Table</h1>
    </div>

    <div class="controls-container">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by Return ID, Customer, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <select class="btn btn-outline-secondary" id="orderBySelect" name="orderBy">
            <option value="">Order By</option>
            <option value="name-asc" <?php if ($orderBy === 'name-asc') echo 'selected'; ?>>Name (A → Z)</option>
            <option value="name-desc" <?php if ($orderBy === 'name-desc') echo 'selected'; ?>>Name (Z → A)</option>
            <option value="price-asc" <?php if ($orderBy === 'price-asc') echo 'selected'; ?>>Total Refunded (Low to High)</option>
            <option value="price-desc" <?php if ($orderBy === 'price-desc') echo 'selected'; ?>>Total Refunded (High to Low)</option>
            <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
            <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
        </select>
        <select class="btn btn-outline-secondary" id="filterBySelect" name="filterBy">
            <option value="">Filter By</option>
            <option value="price-below-1000" <?php if ($filterBy === 'price-below-1000') echo 'selected'; ?>>Below ₱1,000</option>
            <option value="price-1000-5000" <?php if ($filterBy === 'price-1000-5000') echo 'selected'; ?>>₱1,000 - ₱5,000</option>
            <option value="price-5000-10000" <?php if ($filterBy === 'price-5000-10000') echo 'selected'; ?>>₱5,000 - ₱10,000</option>
            <option value="price-above-10000" <?php if ($filterBy === 'price-above-10000') echo 'selected'; ?>>Above ₱10,000</option>
        </select>
    </div>

    <table class="table table-striped table-hover" id="returnsTable">
        <thead>
            <tr>
                <th>Return ID</th>
                <th>Customer Name</th>
                <th>Order #</th>
                <th>Quantity</th>
                <th>Total Refunded</th>
                <th>Status</th>
                <th>Return Date</th>
            </tr>
        </thead>
        <tbody id="returnsTableBody">
            <?php if (empty($returns)): ?>
                <tr><td colspan="7" class="text-center text-muted">No returns available.</td></tr>
            <?php else: ?>
                <?php foreach ($returns as $return): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($return['ReturnID']); ?></td>
                        <td><?php echo htmlspecialchars($return['CustomerName']); ?></td>
                        <td><?php echo htmlspecialchars($return['OrderID']); ?></td>
                        <td><?php echo htmlspecialchars($return['TotalQuantity']); ?></td>
                        <td>₱<?php echo number_format($return['TotalRefunded'], 2); ?></td>
                        <td><?php echo htmlspecialchars($return['Status']); ?></td>
                        <td><?php echo htmlspecialchars($return['ReturnDate']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Details Modal (not connected yet) -->
    <div class="modal fade" id="returnDetailsModal" tabindex="-1" aria-labelledby="returnDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="returnDetailsModalLabel">Return Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Return ID: <span id="detailsReturnId"></span></h6>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="returnItemsTableBody">
                            <tr><td colspan="5" class="text-center">No items available</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', () => dropdown.classList.toggle('active'));
    });

    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const sidebar = document.querySelector('.left-sidebar');
    if (menuToggleBtn && sidebar) {
        menuToggleBtn.addEventListener('click', (event) => {
            sidebar.classList.toggle('active');
            event.stopPropagation();
        });
        document.addEventListener('click', (event) => {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });
        sidebar.addEventListener('click', (event) => event.stopPropagation());
    }

    // Handle search, order, and filter changes
    const searchInput = document.getElementById('searchInput');
    const orderBySelect = document.getElementById('orderBySelect');
    const filterBySelect = document.getElementById('filterBySelect');

    function updateURL() {
        const search = searchInput.value.trim();
        const orderBy = orderBySelect.value;
        const filterBy = filterBySelect.value;
        const url = new URL(window.location);
        url.searchParams.set('search', search);
        url.searchParams.set('orderBy', orderBy);
        url.searchParams.set('filterBy', filterBy);
        window.location = url;
    }

    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateURL, 500);
    });

    orderBySelect.addEventListener('change', updateURL);
    filterBySelect.addEventListener('change', updateURL);
});
</script>
</body>
</html>