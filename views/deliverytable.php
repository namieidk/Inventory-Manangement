<?php
include '../database/database.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log GET parameters for debugging
error_log("GET params in Deliverytable.php: " . print_r($_GET, true));

// Handle stray order_id parameter
if (isset($_GET['order_id']) && !isset($_GET['delivery_id'])) {
    header("Location: Deliverytable.php");
    exit;
}

// Define $filterBy and $orderBy globally with default empty values
$filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : (isset($_GET['filterBy']) ? $_GET['filterBy'] : '');
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : (isset($_GET['orderBy']) ? $_GET['orderBy'] : '');

// Handle AJAX request for delivery details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get_details']) && isset($_GET['delivery_id'])) {
    try {
        $delivery_id = $_GET['delivery_id'];
        $stmt = $conn->prepare("
            SELECT 
                ItemID,
                ProductName,
                Quantity,
                Rate,
                Amount
            FROM DeliveryItems
            WHERE DeliveryID = :delivery_id
        ");
        $stmt->execute([':delivery_id' => $delivery_id]);
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

// Fetch deliveries function (SELECT only)
function fetchDeliveries($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        $sql = "SELECT 
            d.DeliveryID,
            d.SupplierName AS ContactPerson,
            d.DeliveryDate,
            d.Total,
            d.SubTotal,
            d.Discount,
            SUM(di.Quantity) AS TotalQuantity,
            d.Status,
            GROUP_CONCAT(di.ItemID) AS ItemIDs
        FROM Deliveries d
        LEFT JOIN DeliveryItems di ON d.DeliveryID = di.DeliveryID
        WHERE 1=1";
        $whereClause = '';
        $orderClause = " ORDER BY d.DeliveryDate DESC"; // Default to newest
        $params = [];

        // Search functionality
        if (!empty($searchTerm)) {
            $whereClause .= " AND (d.DeliveryID LIKE :search 
                              OR di.ItemID LIKE :search 
                              OR d.SupplierName LIKE :search 
                              OR d.Status LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Filter logic
        switch ($filterBy) {
            case 'price-below-1000':
                $whereClause .= " AND d.Total < 1000";
                break;
            case 'price-1000-5000':
                $whereClause .= " AND d.Total BETWEEN 1000 AND 5000";
                break;
            case 'price-5000-10000':
                $whereClause .= " AND d.Total BETWEEN 5000 AND 10000";
                break;
            case 'price-above-10000':
                $whereClause .= " AND d.Total > 10000";
                break;
            default:
                break;
        }

        // Order logic
        switch ($orderBy) {
            case 'name-asc':
                $orderClause = " ORDER BY d.SupplierName ASC";
                break;
            case 'name-desc':
                $orderClause = " ORDER BY d.SupplierName DESC";
                break;
            case 'price-asc':
                $orderClause = " ORDER BY d.Total ASC";
                break;
            case 'price-desc':
                $orderClause = " ORDER BY d.Total DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY d.DeliveryDate DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY d.DeliveryDate ASC";
                break;
            default:
                break;
        }

        $sql .= $whereClause . "
            GROUP BY d.DeliveryID, d.SupplierName, d.DeliveryDate, d.Total, d.SubTotal, d.Discount, d.Status
        " . $orderClause;

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
    $deliveries = fetchDeliveries($conn, $searchTerm, $orderBy, $filterBy);
    error_log("Search: $searchTerm, OrderBy: $orderBy, FilterBy: $filterBy");
    header('Content-Type: application/json');
    echo json_encode($deliveries);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Table</title>
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
        <li><a href="Reports.php" style="text-decoration: none; color: inherit;"><i class="fas fa-file-invoice-dollar"></i><span> Reports</span></a></li>
        <li><a href="logout.php" style="text-decoration: none; color: inherit;"><i class="fas fa-sign-out-alt"></i><span> Log out</span></a></li>
    </ul>
</div>

<div class="main-content">
    <div class="header-container">
        <button class="menu-btn" id="menuToggleBtn"><i class="fas fa-bars"></i></button>
        <h1>Delivery Table</h1>
    </div>

    <div class="controls-container">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by Delivery ID, Item ID, Supplier, etc.">
        </div>
        <select class="btn btn-outline-secondary" id="orderBySelect" name="orderBy">
            <option value="">Order By</option>
            <option value="name-asc" <?php if ($orderBy === 'name-asc') echo 'selected'; ?>>Name (A → Z)</option>
            <option value="name-desc" <?php if ($orderBy === 'name-desc') echo 'selected'; ?>>Name (Z → A)</option>
            <option value="price-asc" <?php if ($orderBy === 'price-asc') echo 'selected'; ?>>Price (Low to High)</option>
            <option value="price-desc" <?php if ($orderBy === 'price-desc') echo 'selected'; ?>>Price (High to Low)</option>
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

    <table class="table table-striped table-hover" id="deliveriesTable">
        <thead>
            <tr>
                <th>Delivery ID</th>
                <th>Supplier Name</th>
                <th>Order#</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Status</th>
                <th>Delivery Date</th>
            </tr>
        </thead>
        <tbody id="deliveryTableBody">
            <!-- Populated via AJAX -->
        </tbody>
    </table>

    <!-- Details Modal -->
    <div class="modal fade" id="deliveryDetailsModal" tabindex="-1" aria-labelledby="deliveryDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deliveryDetailsModalLabel">Delivery Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Delivery ID: <span id="detailsDeliveryId"></span></h6>
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
                        <tbody id="deliveryItemsTableBody">
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

    function updateTable() {
        const searchTerm = document.getElementById('searchInput').value.trim();
        const orderBy = document.getElementById('orderBySelect').value;
        const filterBy = document.getElementById('filterBySelect').value;

        console.log('Updating table with:', { searchTerm, orderBy, filterBy });

        $.ajax({
            url: 'Deliverytable.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy,
                filterBy: filterBy
            },
            success: function(deliveries) {
                console.log('Deliveries received:', deliveries);
                const tbody = document.getElementById('deliveryTableBody');
                tbody.innerHTML = '';

                if (deliveries.error) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${deliveries.error}</td></tr>`;
                } else if (deliveries.length > 0) {
                    deliveries.forEach(delivery => {
                        const itemIds = delivery.ItemIDs ? delivery.ItemIDs.split(',') : ['N/A'];
                        const itemIdDisplay = itemIds.join(', ');
                        const row = `
                            <tr>
                                <td>${delivery.DeliveryID || 'N/A'}</td>
                                <td>${delivery.ContactPerson || ''}</td>
                                <td><span class="order-link" data-delivery-id="${delivery.DeliveryID}">${itemIdDisplay}</span></td>
                                <td>${delivery.TotalQuantity || 0}</td>
                                <td>₱${parseFloat(delivery.Total || 0).toFixed(2)}</td>
                                <td>${delivery.Status || ''}</td>
                                <td>${delivery.DeliveryDate || ''}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No deliveries available.</td></tr>';
                }

                attachOrderLinkListeners();
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                document.getElementById('deliveryTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading deliveries: ' + xhr.responseText + '</td></tr>';
            }
        });
    }

    // Connect select boxes to table updates
    const orderBySelect = document.getElementById('orderBySelect');
    const filterBySelect = document.getElementById('filterBySelect');
    const searchInput = document.getElementById('searchInput');

    orderBySelect.addEventListener('change', function() {
        console.log('Order By changed to:', this.value);
        updateTable();
    });

    filterBySelect.addEventListener('change', function() {
        console.log('Filter By changed to:', this.value);
        updateTable();
    });

    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            console.log('Search term:', this.value);
            updateTable();
        }, 300);
    });

    // Initial table load
    updateTable();

    function attachOrderLinkListeners() {
        document.querySelectorAll('.order-link').forEach(link => {
            link.addEventListener('click', function() {
                const deliveryId = this.getAttribute('data-delivery-id');
                document.getElementById('detailsDeliveryId').textContent = deliveryId;

                const urlParams = new URLSearchParams();
                urlParams.set('get_details', '1');
                urlParams.set('delivery_id', deliveryId);
                const fetchUrl = `${window.location.pathname}?${urlParams.toString()}`;

                fetch(fetchUrl)
                    .then(response => {
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) throw new Error(data.error || 'Failed to fetch delivery details');
                        const items = data.items;
                        const tbody = document.getElementById('deliveryItemsTableBody');
                        tbody.innerHTML = '';

                        if (items.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="5" class="text-center">No items found for this delivery</td></tr>';
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

                        const modal = new bootstrap.Modal(document.getElementById('deliveryDetailsModal'));
                        modal.show();
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        alert('Failed to load delivery details: ' + error.message);
                        document.getElementById('deliveryItemsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading details: ' + error.message + '</td></tr>';
                        const modal = new bootstrap.Modal(document.getElementById('deliveryDetailsModal'));
                        modal.show();
                    });
            });
        });
    }
});
</script>
</body>
</html>