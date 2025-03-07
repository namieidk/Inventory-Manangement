<?php
include '../database/database.php';
include '../database/utils.php'; // Include database connection
session_start(); // Start session

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
logAction($conn, $userId, "Accessed Invoice Page", "User accessed the invoice page");

// Placeholder for fetching invoices
$invoices = [];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    // Ensure PDO connection is available
    if (!isset($conn) || !$conn) {
        die("Database connection not established.");
    }

    try {
        // Start a transaction
        $conn->beginTransaction();

        // Main invoice details
        $payment_type = $_POST['payment_type'] ?? '';
        $client_name = $_POST['charge_to'] ?? '';
        $date = $_POST['date'] ?? '';
        $tin = $_POST['tin'] ?? null;  // Can be NULL
        $payment_terms = $_POST['payment_terms'] ?? '';
        $total_amount = 0.00; // We'll calculate this from items

        // Validate required fields
        if (empty($client_name) || empty($date) || empty($payment_terms) || empty($payment_type)) {
            throw new Exception("All required fields must be filled.");
        }

        // Calculate total from items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                $total_amount += floatval($item['amount'] ?? 0.00);
            }
        }

        // Insert into Invoice table
        $sql = "INSERT INTO Invoice (payment_type, client_name, date, tin, payment_terms, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$payment_type, $client_name, $date, $tin, $payment_terms, $total_amount]);
        $invoice_id = $conn->lastInsertId();

        // Insert invoice items
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_sql = "INSERT INTO InvoiceItems (invoice_id, product, quantity, description, unit_price, amount) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            
            foreach ($_POST['items'] as $item) {
                $product = $item['product'] ?? '';
                $quantity = $item['quantity'] ?? 0;
                $description = $item['description'] ?? null;  // Can be NULL
                $unit_price = $item['unit_price'] ?? 0.00;
                $amount = $item['amount'] ?? 0.00;

                if (!empty($product) && $quantity > 0 && $unit_price >= 0) {
                    $item_stmt->execute([$invoice_id, $product, $quantity, $description, $unit_price, $amount]);
                }
            }
        }

        // Commit transaction
        $conn->commit();
        echo "<script>alert('Invoice saved successfully!'); window.location.href = 'Invoice.php';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('Error saving invoice: " . addslashes($e->getMessage()) . "');</script>";
    }
}

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
                    i.id,
                    i.payment_type,
                    i.client_name,
                    i.total_amount,
                    i.payment_terms,
                    i.date,
                    COUNT(ii.id) as item_count
                FROM Invoice i
                LEFT JOIN InvoiceItems ii ON i.id = ii.invoice_id
                WHERE 1=1";
        $params = [];

        if (!empty($searchTerm)) {
            $sql .= " AND (i.id LIKE :search 
                        OR i.client_name LIKE :search 
                        OR i.payment_type LIKE :search 
                        OR i.payment_terms LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        if ($filterBy) {
            switch ($filterBy) {
                case 'Below ₱1,000':
                    $sql .= " AND i.total_amount < 1000";
                    break;
                case '₱5,000 - ₱10,000':
                    $sql .= " AND i.total_amount BETWEEN 5000 AND 10000";
                    break;
                case 'Above ₱10,000':
                    $sql .= " AND i.total_amount > 10000";
                    break;
            }
        }

        $sql .= " GROUP BY i.id, i.payment_type, i.client_name, i.total_amount, i.payment_terms, i.date";

        if ($orderBy) {
            switch ($orderBy) {
                case 'client_asc':
                    $sql .= " ORDER BY i.client_name ASC";
                    break;
                case 'client_desc':
                    $sql .= " ORDER BY i.client_name DESC";
                    break;
                case 'amount_asc':
                    $sql .= " ORDER BY i.total_amount ASC";
                    break;
                case 'amount_desc':
                    $sql .= " ORDER BY i.total_amount DESC";
                    break;
                case 'date_desc':
                    $sql .= " ORDER BY i.date DESC";
                    break;
                case 'date_asc':
                    $sql .= " ORDER BY i.date ASC";
                    break;
                default:
                    $sql .= " ORDER BY i.date DESC";
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

// Fetch invoice data from database with item count for initial load
try {
    $sql = "SELECT 
                i.id,
                i.payment_type,
                i.client_name,
                i.total_amount,
                i.payment_terms,
                i.date,
                COUNT(ii.id) as item_count
            FROM Invoice i
            LEFT JOIN InvoiceItems ii ON i.id = ii.invoice_id
            GROUP BY i.id, i.payment_type, i.client_name, i.total_amount, i.payment_terms, i.date
            ORDER BY i.date DESC";
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
        .action-buttons {
            display: flex;
            gap: 10px;
            white-space: nowrap;
            margin-right: 15px;
        }
        .action-buttons .btn {
            padding: 6px 12px;
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
        .item-table th, .item-table td {
            padding: 6px;
            vertical-align: middle;
        }
        .item-table input[type="text"],
        .item-table input[type="number"] {
            width: 100%;
            padding: 4px;
        }
        .order-link {
            color: #007bff;
            text-decoration: underline;
            cursor: pointer;
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
    <h1>Invoice</h1>
    <div class="header-row">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" placeholder="Search..." id="searchInput" name="search">
        </div>
        <div class="action-buttons">
            <button class="btn btn-primary" id="newBtn">New <i class="fas fa-plus"></i></button>
        </div>
        <div class="select-container">
            <select class="btn btn-outline-secondary" id="orderBySelect">
                <option value="">Order By</option>
                <option value="client_asc">Ascending (A → Z)</option>
                <option value="client_desc">Descending (Z → A)</option>
                <option value="amount_asc">Low Price (Ascending)</option>
                <option value="amount_desc">High Price (Descending)</option>
                <option value="date_desc">Newest</option>
                <option value="date_asc">Oldest</option>
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
                <th>Invoice#</th>
                <th>Order#</th>
                <th>Payment Type</th>
                <th>Client Name</th>
                <th>Amount</th>
                <th>Payment Terms</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="invoiceTableBody">
            <?php if (empty($invoices)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No invoice available. Input new invoice.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                        <td>
                            <span class="order-link" data-invoice-id="<?php echo $invoice['id']; ?>" data-bs-toggle="modal" data-bs-target="#itemsModal">
                                <?php echo htmlspecialchars($invoice['item_count']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($invoice['payment_type']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                        <td><?php echo number_format($invoice['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($invoice['payment_terms']); ?></td>
                        <td><?php echo htmlspecialchars($invoice['date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- New Invoice Modal -->
    <div class="modal fade" id="newInvoiceModal" tabindex="-1" aria-labelledby="newInvoiceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="newInvoiceModalLabel">New Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newInvoiceForm" method="post">
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label for="paymentType" class="form-label">Payment Type</label>
                                <select class="form-control" id="paymentType" name="payment_type" required>
                                    <option value="charge">Charge</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="clientName" class="form-label">Client Name</label>
                                <input type="text" class="form-control" id="clientName" name="charge_to" required>
                            </div>
                            <div class="col-md-6">
                                <label for="invoiceDate" class="form-label">Date</label>
                                <input type="date" class="form-control" id="invoiceDate" name="date" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="invoiceTin" class="form-label">TIN</label>
                                <input type="text" class="form-control" id="invoiceTin" name="tin">
                            </div>
                            <div class="col-md-6">
                                <label for="invoicePaymentTerms" class="form-label">Payment Terms</label>
                                <input type="text" class="form-control" id="invoicePaymentTerms" name="payment_terms" required>
                            </div>
                        </div>
                        <h6>Items</h6>
                        <table class="table table-striped item-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Description</th>
                                    <th>Unit Price</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody id="invoiceItemsTableBody">
                                <tr>
                                    <td><input type="text" class="form-control" name="items[0][product]" required></td>
                                    <td><input type="number" class="form-control" name="items[0][quantity]" min="1" required></td>
                                    <td><input type="text" class="form-control" name="items[0][description]"></td>
                                    <td><input type="number" class="form-control" name="items[0][unit_price]" step="0.01" required></td>
                                    <td><input type="number" class="form-control" name="items[0][amount]" readonly></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="btn btn-success btn-sm" id="addInvoiceItemBtn">Add Item</button>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save" class="btn btn-primary">Save Invoice</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

    // New button functionality
    document.getElementById('newBtn').addEventListener('click', function() {
        const modal = new bootstrap.Modal(document.getElementById('newInvoiceModal'));
        modal.show();
    });

    // Function to calculate amount
    function calculateAmount(quantityInput, unitPriceInput, amountInput) {
        const quantity = parseFloat(quantityInput.value) || 0;
        const unitPrice = parseFloat(unitPriceInput.value) || 0;
        amountInput.value = (quantity * unitPrice).toFixed(2);
    }

    // Initialize calculation for items
    function initializeItemCalculation(tbodyId) {
        const tbody = document.getElementById(tbodyId);
        tbody.querySelectorAll('tr').forEach(row => {
            const quantityInput = row.querySelector('[name$="[quantity]"]');
            const unitPriceInput = row.querySelector('[name$="[unit_price]"]');
            const amountInput = row.querySelector('[name$="[amount]"]');
            quantityInput.addEventListener('input', () => calculateAmount(quantityInput, unitPriceInput, amountInput));
            unitPriceInput.addEventListener('input', () => calculateAmount(quantityInput, unitPriceInput, amountInput));
            calculateAmount(quantityInput, unitPriceInput, amountInput);
        });
    }

    initializeItemCalculation('invoiceItemsTableBody');

    // Add new item row in Invoice modal
    let invoiceItemCount = 1;
    document.getElementById('addInvoiceItemBtn').addEventListener('click', function() {
        const tbody = document.getElementById('invoiceItemsTableBody');
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><input type="text" class="form-control" name="items[${invoiceItemCount}][product]" required></td>
            <td><input type="number" class="form-control" name="items[${invoiceItemCount}][quantity]" min="1" required></td>
            <td><input type="text" class="form-control" name="items[${invoiceItemCount}][description]"></td>
            <td><input type="number" class="form-control" name="items[${invoiceItemCount}][unit_price]" step="0.01" required></td>
            <td><input type="number" class="form-control" name="items[${invoiceItemCount}][amount]" readonly></td>
        `;
        tbody.appendChild(newRow);

        const quantityInput = newRow.querySelector(`[name="items[${invoiceItemCount}][quantity]"]`);
        const unitPriceInput = newRow.querySelector(`[name="items[${invoiceItemCount}][unit_price]"]`);
        const amountInput = newRow.querySelector(`[name="items[${invoiceItemCount}][amount]"]`);
        quantityInput.addEventListener('input', () => calculateAmount(quantityInput, unitPriceInput, amountInput));
        unitPriceInput.addEventListener('input', () => calculateAmount(quantityInput, unitPriceInput, amountInput));
        calculateAmount(quantityInput, unitPriceInput, amountInput);

        invoiceItemCount++;
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
                console.log('Invoices received:', invoices); // Debug log
                const tbody = document.getElementById('invoiceTableBody');
                tbody.innerHTML = '';

                if (invoices.error) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${invoices.error}</td></tr>`;
                    return;
                }

                if (invoices.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No invoice available. Input new invoice.</td></tr>';
                    return;
                }

                invoices.forEach(invoice => {
                    const row = `
                        <tr>
                            <td>${invoice.id}</td>
                            <td><span class="order-link" data-invoice-id="${invoice.id}" data-bs-toggle="modal" data-bs-target="#itemsModal">${invoice.item_count}</span></td>
                            <td>${invoice.payment_type || ''}</td>
                            <td>${invoice.client_name || ''}</td>
                            <td>${parseFloat(invoice.total_amount || 0).toFixed(2)}</td>
                            <td>${invoice.payment_terms || ''}</td>
                            <td>${invoice.date || ''}</td>
                        </tr>
                    `;
                    tbody.innerHTML += row;
                });

                // Reattach order link listeners
                attachOrderLinkListeners();
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                document.getElementById('invoiceTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading invoices</td></tr>';
            }
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300); // 300ms debounce
    });

    // Sorting and filtering listeners
    orderBySelect.addEventListener('change', updateTable);
    filterBySelect.addEventListener('change', updateTable);

    // Handle clicking Order# to show items
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
                    <td>${item.product ? item.product : ''}</td>
                    <td>${item.quantity || 0}</td>
                    <td>${item.description ? item.description : ''}</td>
                    <td>${parseFloat(item.unit_price || 0).toFixed(2)}</td>
                    <td>${parseFloat(item.amount || 0).toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('itemsTableBody').innerHTML = '<tr><td colspan="5" class="text-center">Error loading items.</td></tr>';
        });
    }

    // Initial table load and event listeners
    updateTable();
    attachOrderLinkListeners();
});
</script>
</body>
</html>