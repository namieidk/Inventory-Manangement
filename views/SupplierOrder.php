<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than 5 minutes ago
if (!isset($_SESSION['last_SupplierOrder_log']) || (time() - $_SESSION['last_SupplierOrder_log']) > 300) {
    logAction($conn, $userId, "Accessed Supplier Order Page", "User accessed the Supplier Order page");
    $_SESSION['last_SupplierOrder_log'] = time();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle AJAX request for order details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_details']) && isset($_GET['order_id'])) {
    try {
        $order_id = $_GET['order_id'];
        $stmt = $conn->prepare("
            SELECT 
                ItemID,
                ProductName,
                Quantity,
                Rate,
                Amount
            FROM SupplierOrderItems
            WHERE OrderID = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'items' => $items]);
        exit();
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Unified fetch function for both initial load and AJAX
function fetchOrders($conn, $searchTerm = '', $orderBy = '', $filterBy = '', $subFilter = '') {
    try {
        $sql = "SELECT 
            so.OrderID,
            so.SupplierName AS ContactPerson,
            so.OrderDate,
            so.DeliveryDate,
            so.Total,
            so.SubTotal,
            so.Discount,
            so.Status
        FROM SupplierOrders so
        WHERE 1=1";
        $whereClause = '';
        $orderClause = " ORDER BY so.OrderDate DESC"; // Default
        $params = [];

        // Search functionality
        if (!empty($searchTerm)) {
            $whereClause .= " AND (so.OrderID LIKE :search 
                              OR so.SupplierName LIKE :search 
                              OR so.Status LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Filter logic
        switch ($filterBy) {
            case 'status':
                if (!empty($subFilter)) {
                    $whereClause .= " AND so.Status = :subFilter";
                    $params[':subFilter'] = $subFilter;
                }
                break;
        }

        // Order logic
        switch ($orderBy) {
            case 'name-asc':
                $orderClause = " ORDER BY so.SupplierName ASC";
                break;
            case 'name-desc':
                $orderClause = " ORDER BY so.SupplierName DESC";
                break;
            case 'price-asc':
                $orderClause = " ORDER BY so.Total ASC";
                break;
            case 'price-desc':
                $orderClause = " ORDER BY so.Total DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY so.OrderDate DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY so.OrderDate ASC";
                break;
        }

        $sql .= $whereClause . " " . $orderClause;

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ['error' => "Database error: " . $e->getMessage()];
    }
}

// Handle AJAX search request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
    $filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : '';
    $subFilter = isset($_POST['subFilter']) ? $_POST['subFilter'] : '';
    $orders = fetchOrders($conn, $searchTerm, $orderBy, $filterBy, $subFilter);
    error_log("Search: $searchTerm, OrderBy: $orderBy, FilterBy: $filterBy, SubFilter: $subFilter"); // Debug log to server
    header('Content-Type: application/json');
    echo json_encode($orders);
    exit;
}

$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
$filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : '';
$subFilter = isset($_POST['subFilter']) ? $_POST['subFilter'] : '';
$orders = fetchOrders($conn, $searchTerm, $orderBy, $filterBy, $subFilter);
if (isset($orders['error'])) {
    $error = $orders['error'];
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Order Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/CustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .left-sidebar {
            position: fixed;
            top: 0;
            left: -250px;
            width: 250px;
            height: 100%;
            background-color: #343F79;
            transition: left 0.3s ease;
            z-index: 1000;
        }
        .left-sidebar.active {
            left: 0;
        }
        .main-content {
            width: 100%;
            max-width: 100%;
            margin-left: auto;
            margin-right: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        .menu-btn {
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
            margin-right: 10px;
        }
        .header-container {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .search-container {
            position: relative;
            width: 300px;
            margin-left: 45px;
            margin-right: 590px;
        }
        .search-container .form-control {
            padding-left: 35px;
        }
        .search-container .fa-search {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .order-link {
            cursor: pointer;
            color: #007bff;
            text-decoration: underline;
        }
        .order-link:hover {
            color: #0056b3;
        }
        .controls-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 15px;
            width: 100%;
        }
        .btn-dark.mr-2 {
            margin-right: 15px;
        }
        .btn-outline-secondary.mr-2 {
            margin-right: 15px;
        }
        select.btn.btn-outline-secondary {
            appearance: auto;
            padding: 5px;
            min-width: 150px;
        }
        #subFilterContainer {
            margin-left: 10px;
            display: none;
        }
        #subFilterSelect {
            appearance: auto;
            padding: 5px;
            min-width: 150px;
        }
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
        <h1>Supplier Order</h1>
    </div>

    <div class="controls-container">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by Order ID, Supplier, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <button class="btn btn-dark mr-2" id="newProductBtn">New <i class="fa fa-plus"></i></button>
        <form name="filterForm" id="filterForm" method="post" class="d-flex align-items-center">
            <select class="btn btn-outline-secondary mr-2" name="orderBy" id="orderBySelect" onchange="updateTable()">
                <option value="">Order By</option>
                <option value="name-asc" <?php if ($orderBy === 'name-asc') echo 'selected'; ?>>Ascending (A → Z)</option>
                <option value="name-desc" <?php if ($orderBy === 'name-desc') echo 'selected'; ?>>Descending (Z → A)</option>
                <option value="price-asc" <?php if ($orderBy === 'price-asc') echo 'selected'; ?>>Low Price (Ascending)</option>
                <option value="price-desc" <?php if ($orderBy === 'price-desc') echo 'selected'; ?>>High Price (Descending)</option>
                <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
                <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
            </select>
            <select class="btn btn-outline-secondary mr-2" name="filterBy" id="filterBySelect" onchange="handleFilterChange()">
                <option value="">Filter By</option>
                <option value="status" <?php if ($filterBy === 'status') echo 'selected'; ?>>Status</option>
            </select>
            <div id="subFilterContainer" style="display: <?php echo !empty($filterBy) ? 'inline-block' : 'none'; ?>;">
                <select id="subFilterSelect" class="btn btn-outline-secondary" onchange="updateTable()">
                    <!-- Options populated by JavaScript -->
                </select>
            </div>
        </form>
    </div>

    <table class="table table-striped table-hover" id="ordersTable">
        <thead>
            <tr>
                <th>Supplier Order ID</th>
                <th>Supplier Name</th>
                <th>Total</th>
                <th>Status</th>
                <th>Estimated Date</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="orderTableBody">
            <?php if (isset($error)): ?>
                <tr><td colspan="6" class="text-center text-danger"><?= htmlspecialchars($error) ?></td></tr>
            <?php elseif (!empty($orders)): ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars($order['OrderID'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($order['ContactPerson'] ?? '') ?></td>
                        <td>₱<?= number_format($order['Total'] ?? 0, 2) ?></td>
                        <td><?= htmlspecialchars($order['Status'] ?? '') ?></td>
                        <td><?= htmlspecialchars($order['DeliveryDate'] ?? '') ?></td>
                        <td>
                            <button class="btn btn-sm btn-success delivery-btn" 
                                    data-order-id="<?= htmlspecialchars($order['OrderID']) ?>"
                                    title="View Delivery">
                                <i class="fas fa-truck"></i>
                            </button>
                            <span class="order-link ms-2" data-order-id="<?= htmlspecialchars($order['OrderID']) ?>">
                                <i class="fas fa-info-circle" title="View Details"></i>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="text-center text-muted">No supplier orders available. Input new order.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailsModalLabel">Supplier Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Order ID: <span id="detailsOrderId"></span></h6>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Item ID</th>
                                <th>Product Name</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="orderItemsTableBody">
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
    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', () => dropdown.classList.toggle('active'));
    });

    // New button redirect
    document.getElementById('newProductBtn').addEventListener('click', () => {
        window.location.href = 'NewSupplierOrder.php';
    });

    // Sidebar toggle
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

    // Define sub-filters
    const subFilters = {
        'status': [
            { value: '', text: 'Select Status' },
            { value: 'Pending', text: 'Pending' },
            { value: 'Completed', text: 'Completed' },
            { value: 'Cancelled', text: 'Cancelled' },
            { value: 'On Hold', text: 'On Hold' }
        ]
    };

    // Handle filter change
    function handleFilterChange() {
        const filterValue = document.getElementById('filterBySelect').value;
        const subFilterContainer = document.getElementById('subFilterContainer');
        const subFilterSelect = document.getElementById('subFilterSelect');
        
        subFilterContainer.style.display = filterValue ? 'inline-block' : 'none';
        subFilterSelect.innerHTML = '';
        
        if (filterValue) {
            const options = subFilters[filterValue];
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.text = opt.text;
                if (opt.value === '<?php echo $subFilter; ?>') option.selected = true;
                subFilterSelect.appendChild(option);
            });
        }
        updateTable();
    }

    // Table update function
    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.trim();
        const orderBy = document.querySelector('select[name="orderBy"]').value;
        const filterBy = document.querySelector('select[name="filterBy"]').value;
        const subFilter = filterBy ? document.getElementById('subFilterSelect').value : '';

        console.log('Updating table with:', { searchTerm, orderBy, filterBy, subFilter }); // Debug

        $.ajax({
            url: 'SupplierOrder.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy,
                filterBy: filterBy,
                subFilter: subFilter
            },
            success: function(orders) {
                console.log('Orders received:', orders); // Debug
                const tbody = document.getElementById('orderTableBody');
                tbody.innerHTML = '';

                if (orders.error) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${orders.error}</td></tr>`;
                } else if (orders.length > 0) {
                    orders.forEach(order => {
                        const row = `
                            <tr>
                                <td>${order.OrderID || 'N/A'}</td>
                                <td>${order.ContactPerson || ''}</td>
                                <td>₱${parseFloat(order.Total || 0).toFixed(2)}</td>
                                <td>${order.Status || ''}</td>
                                <td>${order.DeliveryDate || ''}</td>
                                <td>
                                    <button class="btn btn-sm btn-success delivery-btn" 
                                            data-order-id="${order.OrderID}"
                                            title="View Delivery">
                                        <i class="fas fa-truck"></i>
                                    </button>
                                    <span class="order-link ms-2" data-order-id="${order.OrderID}">
                                        <i class="fas fa-info-circle" title="View Details"></i>
                                    </span>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No supplier orders available. Input new order.</td></tr>';
                }

                // Reattach event listeners
                attachDeliveryButtonListeners();
                attachOrderLinkListeners();
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                document.getElementById('orderTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading orders: ' + xhr.responseText + '</td></tr>';
            }
        });
    }

    // Event listeners
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300); // Debounce 300ms
    });

    document.querySelector('select[name="orderBy"]').addEventListener('change', updateTable);
    document.getElementById('filterBySelect').addEventListener('change', handleFilterChange);
    document.getElementById('subFilterSelect').addEventListener('change', updateTable);

    // Initial load
    handleFilterChange();
    updateTable();

    // Delivery button functionality
    function attachDeliveryButtonListeners() {
        document.querySelectorAll('.delivery-btn').forEach(button => {
            button.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                window.location.href = `Delivery.php?order_id=${orderId}`;
            });
        });
    }

    // Order details link functionality
    function attachOrderLinkListeners() {
        document.querySelectorAll('.order-link').forEach(link => {
            link.addEventListener('click', function() {
                const orderId = this.getAttribute('data-order-id');
                document.getElementById('detailsOrderId').textContent = orderId;

                const urlParams = new URLSearchParams();
                urlParams.set('get_details', '1');
                urlParams.set('order_id', orderId);
                const fetchUrl = `${window.location.pathname}?${urlParams.toString()}`;

                fetch(fetchUrl)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) throw new Error(data.error || 'Failed to fetch order details');
                        const items = data.items;
                        const tbody = document.getElementById('orderItemsTableBody');
                        tbody.innerHTML = '';

                        if (items.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No items found for this order</td></tr>';
                        } else {
                            items.forEach(item => {
                                const row = `
                                    <tr>
                                        <td>${item.ItemID || 'N/A'}</td>
                                        <td>${item.ProductName || 'N/A'}</td>
                                        <td>${item.Quantity || 0}</td>
                                        <td>₱${item.Rate ? parseFloat(item.Rate).toFixed(2) : '0.00'}</td>
                                        <td>₱${item.Amount ? parseFloat(item.Amount).toFixed(2) : '0.00'}</td>
                                    </tr>
                                `;
                                tbody.innerHTML += row;
                            });
                        }

                        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Failed to load order details: ' + error.message);
                        document.getElementById('orderItemsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading details: ' + error.message + '</td></tr>';
                        const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                        modal.show();
                    });
            });
        });
    }
});
</script>
</body>
</html>