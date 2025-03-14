<?php
include '../database/database.php';
include '../database/utils.php'; // Include database connection
session_start(); // Start session

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than 300 seconds ago (5 minutes)
if (!isset($_SESSION['last_invoice_log']) || (time() - $_SESSION['last_invoice_log']) > 300) {
    logAction($conn, $userId, "Accessed Invoice Page", "User accessed the invoice page");
    $_SESSION['last_invoice_log'] = time();
}

// Placeholder for fetching invoices
$invoices = [];

// AJAX handler for fetching invoice items
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['get_items'])) {
    $invoice_id = $_POST['invoice_id'];
    try {
        $sql = "SELECT product, quantity, description, unit_price, amount 
                FROM InvoiceItems 
                WHERE invoice_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$invoice_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($items);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// AJAX handler for searching invoices
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'search') {
    try {
        $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
        $orderBy = isset($_POST['order_by']) ? $_POST['order_by'] : 'date_desc';
        $filterBy = isset($_POST['filter_by']) ? $_POST['filter_by'] : '';

        $sql = "SELECT 
                    invoice_id,
                    reference_number,
                    payment_type,
                    client_name,
                    total_amount,
                    payment_terms,
                    date,
                    status
                FROM Invoice
                WHERE 1=1";
        $params = [];

        if (!empty($searchTerm)) {
            $sql .= " AND (reference_number LIKE :search 
                        OR invoice_id LIKE :search 
                        OR client_name LIKE :search 
                        OR payment_type LIKE :search 
                        OR payment_terms LIKE :search 
                        OR status LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        if ($filterBy) {
            switch ($filterBy) {
                case 'Below ₱1,000':
                    $sql .= " AND total_amount < 1000";
                    break;
                case '₱5,000 - ₱10,000':
                    $sql .= " AND total_amount BETWEEN 5000 AND 10000";
                    break;
                case 'Above ₱10,000':
                    $sql .= " AND total_amount > 10000";
                    break;
            }
        }

        if ($orderBy) {
            switch ($orderBy) {
                case 'client_asc':
                    $sql .= " ORDER BY client_name ASC";
                    break;
                case 'client_desc':
                    $sql .= " ORDER BY client_name DESC";
                    break;
                case 'amount_asc':
                    $sql .= " ORDER BY total_amount ASC";
                    break;
                case 'amount_desc':
                    $sql .= " ORDER BY total_amount DESC";
                    break;
                case 'date_desc':
                    $sql .= " ORDER BY date DESC";
                    break;
                case 'date_asc':
                    $sql .= " ORDER BY date ASC";
                    break;
                default:
                    $sql .= " ORDER BY date DESC";
            }
        }

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        echo json_encode($invoices);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Fetch invoice data for initial load
try {
    $sql = "SELECT 
                invoice_id,
                reference_number,
                payment_type,
                client_name,
                total_amount,
                payment_terms,
                date,
                status
            FROM Invoice
            ORDER BY date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo "<script>alert('Error fetching invoices: " . addslashes($e->getMessage()) . "');</script>";
    $invoices = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Invoice.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: nowrap;
            width: 100%;
            margin-bottom: 20px;
        }
        .search-container {
            position: relative;
            flex: 1;
            min-width: 150px;
            max-width: 550px;
            margin-right: 10px;
        }
        .search-container .form-control {
            padding-left: 35px;
            width: 100%;
        }
        .search-container .fa-search {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .select-container {
            display: flex;
            gap: 10px;
            white-space: nowrap;
        }
        .select-container select {
            padding: 6px 12px;
            min-width: 150px;
        }
        .order-link {
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
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
    <h1>Invoice</h1>
    <div class="header-row">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" placeholder="Search..." id="searchInput" name="search">
        </div>
        <div class="select-container">
            <select class="btn btn-outline-secondary" id="orderBySelect">
                <option value="">Order By</option>
                <option value="client_asc">Client Name (A → Z)</option>
                <option value="client_desc">Client Name (Z → A)</option>
                <option value="amount_asc">Total Amount (Low to High)</option>
                <option value="amount_desc">Total Amount (High to Low)</option>
                <option value="date_desc">Delivery Date (Newest)</option>
                <option value="date_asc">Delivery Date (Oldest)</option>
            </select>
            <select class="btn btn-outline-secondary" id="filterBySelect">
                <option value="">Filter By</option>
                <option value="Below ₱1,000">Below ₱1,000</option>
                <option value="₱5,000 - ₱10,000">₱5,000 - ₱10,000</option>
                <option value="Above ₱10,000">Above ₱10,000</option>
            </select>
        </div>
    </div>

    <table class="table table-striped table-hover" id="invoiceTable">
        <thead>
            <tr>
                <th>Invoice ID</th>
                <th>Reference Number</th>
                <th>Client Name</th>
                <th>Total Amount</th>
                <th>Payment Type</th>
                <th>Payment Terms</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="invoiceTableBody">
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">No invoices available.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['invoice_id']); ?></td>
                        <td>
                            <span class="order-link" data-invoice-id="<?php echo $invoice['invoice_id']; ?>" data-bs-toggle="modal" data-bs-target="#itemsModal">
                                <?php echo htmlspecialchars($invoice['reference_number']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                        <td>₱<?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($invoice['payment_type']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['payment_terms']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['date']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['status']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-warning btn-sm edit-btn" data-invoice-id="<?php echo $invoice['invoice_id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-primary btn-sm return-btn" data-invoice-id="<?php echo $invoice['invoice_id']; ?>">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Items Detail Modal -->
    <div class="modal fade" id="itemsModal" tabindex="-1" aria-labelledby="itemsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemsModalLabel">Invoice Items</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Description</th>
                                <th>Unit Price</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <!-- Items will be loaded here via JavaScript -->
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
    var dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function(dropdown) {
        var toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', function() {
            dropdown.classList.toggle('active');
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    const orderBySelect = document.getElementById('orderBySelect');
    const filterBySelect = document.getElementById('filterBySelect');
    let searchTimeout;

    function updateTable() {
        const searchTerm = searchInput.value.trim();
        const orderBy = orderBySelect.value;
        const filterBy = filterBySelect.value;

        $.ajax({
            url: 'Invoice.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                order_by: orderBy,
                filter_by: filterBy
            },
            success: function(invoices) {
                const tbody = document.getElementById('invoiceTableBody');
                tbody.innerHTML = '';

                if (invoices.error) {
                    tbody.innerHTML = `<tr><td colspan="9" class="text-center text-danger">${invoices.error}</td></tr>`;
                    return;
                }

                if (invoices.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No invoices available.</td></tr>';
                    return;
                }

                invoices.forEach(invoice => {
                    const row = `
                        <tr>
                            <td>${invoice.invoice_id}</td>
                            <td><span class="order-link" data-invoice-id="${invoice.invoice_id}" data-bs-toggle="modal" data-bs-target="#itemsModal">${invoice.reference_number || ''}</span></td>
                            <td>${invoice.client_name || ''}</td>
                            <td>₱${parseFloat(invoice.total_amount || 0).toFixed(2)}</td>
                            <td>${invoice.payment_type || ''}</td>
                            <td>${invoice.payment_terms || ''}</td>
                            <td>${invoice.date || ''}</td>
                            <td>${invoice.status || 'Pending'}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-warning btn-sm edit-btn" data-invoice-id="${invoice.invoice_id}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-primary btn-sm return-btn" data-invoice-id="${invoice.invoice_id}">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });

                attachOrderLinkListeners();
                attachEditButtonListeners();
                attachReturnButtonListeners();
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                document.getElementById('invoiceTableBody').innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading invoices</td></tr>';
            }
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300);
    });

    // Sorting and filtering listeners
    orderBySelect.addEventListener('change', updateTable);
    filterBySelect.addEventListener('change', updateTable);

    // Handle clicking Reference Number to show items
    function attachOrderLinkListeners() {
        document.querySelectorAll('.order-link').forEach(link => {
            link.addEventListener('click', function() {
                const invoiceId = this.getAttribute('data-invoice-id');
                fetchItems(invoiceId);
            });
        });
    }

    function fetchItems(invoiceId) {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'get_items=1&invoice_id=' + encodeURIComponent(invoiceId)
        })
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';
            
            if (data.error) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Error: ' + data.error + '</td></tr>';
                return;
            }

            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">No items found for this invoice.</td></tr>';
                return;
            }

            data.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.product || ''}</td>
                    <td>${item.quantity || 0}</td>
                    <td>${item.description || ''}</td>
                    <td>₱${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                    <td>₱${parseFloat(item.amount || 0).toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading items.</td></tr>';
        });
    }

    // Redirect to returnProducts.php when edit button is clicked
    function attachEditButtonListeners() {
        document.querySelectorAll('.edit-btn').forEach(button => {
            button.addEventListener('click', function() {
                const invoiceId = this.getAttribute('data-invoice-id');
                window.location.href = `returnProducts.php?invoice_id=${invoiceId}`;
            });
        });
    }

    // Redirect to returnProducts.php when return button is clicked
    function attachReturnButtonListeners() {
        document.querySelectorAll('.return-btn').forEach(button => {
            button.addEventListener('click', function() {
                const invoiceId = this.getAttribute('data-invoice-id');
                window.location.href = `returnProducts.php?invoice_id=${invoiceId}`;
            });
        });
    }

    // Initial table load and event listeners
    updateTable();
    attachOrderLinkListeners();
    attachEditButtonListeners();
    attachReturnButtonListeners();
});
</script>
</body>
</html>