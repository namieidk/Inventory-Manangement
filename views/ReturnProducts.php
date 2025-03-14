<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
if (!isset($_SESSION['last_return_log']) || (time() - $_SESSION['last_return_log']) > 300) {
    logAction($conn, $userId, "Accessed New Return Page", "User accessed the new return page");
    $_SESSION['last_return_log'] = time();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Check database.php configuration.");
}

// Get invoice_id from URL if provided, and map it to order_id
$prefill_invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : null;
$prefill_order_id = null;
$prefill_customer_name = '';
$prefill_reference_number = '';
if ($prefill_invoice_id) {
    try {
        $stmt = $conn->prepare("
            SELECT i.tin, i.client_name, i.reference_number
            FROM Invoice i
            WHERE i.invoice_id = :invoice_id
        ");
        $stmt->execute([':invoice_id' => $prefill_invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invoice) {
            if (preg_match('/Order-(\d+)/', $invoice['tin'], $matches)) {
                $prefill_order_id = (int)$matches[1];
            }
            $prefill_customer_name = $invoice['client_name'];
            $prefill_reference_number = $invoice['reference_number'];
        } else {
            echo "<script>alert('Invoice not found.');</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Error fetching invoice details: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Fetch products for the dropdown
try {
    $stmt = $conn->prepare("SELECT id, product_name, price FROM products WHERE status = 'active'");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}

// Fetch customer orders for the dropdown
try {
    $stmt = $conn->prepare("SELECT OrderID, CustomerName, OrderDate FROM CustomerOrders ORDER BY OrderDate DESC");
    $stmt->execute();
    $customer_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $customer_orders = [];
    echo "<script>alert('Error fetching customer orders: " . addslashes($e->getMessage()) . "');</script>";
}

// Fetch order items if order_id is determined from invoice_id
$prefill_items = [];
if ($prefill_order_id) {
    try {
        $stmt = $conn->prepare("
            SELECT ProductName, Quantity, Rate AS UnitPrice, Amount 
            FROM CustomerOrderItems 
            WHERE OrderID = :order_id
        ");
        $stmt->execute([':order_id' => $prefill_order_id]);
        $prefill_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<script>alert('Error fetching order items: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    try {
        $conn->beginTransaction();

        $order_id = $_POST['order_id'] ?? null;
        $return_reference = $_POST['return_reference'] ?? null; // Nullable in DB
        $return_date = $_POST['return_date'] ?? null;
        $return_reason = $_POST['return_reason'] ?? null; // Nullable in DB
        $return_status = $_POST['return_status'] ?? 'Pending';
        $total_refunded = (float)($_POST['total'] ?? 0.00);

        // Validation for required fields
        if (empty($order_id) || empty($return_date)) {
            throw new Exception("Customer Order and Return Date are required.");
        }

        // Fetch CustomerName based on OrderID
        $stmt = $conn->prepare("SELECT CustomerName FROM CustomerOrders WHERE OrderID = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $customer_name = $stmt->fetchColumn();
        if ($customer_name === false) {
            throw new Exception("Invalid Order ID selected.");
        }

        // Insert into Returns table
        $sql = "INSERT INTO Returns (order_id, return_reference, customer_name, return_date, return_reason, total_refunded, status) 
                VALUES (:order_id, :return_reference, :customer_name, :return_date, :return_reason, :total_refunded, :status)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':order_id' => $order_id,
            ':return_reference' => $return_reference,
            ':customer_name' => $customer_name,
            ':return_date' => $return_date,
            ':return_reason' => $return_reason,
            ':total_refunded' => $total_refunded,
            ':status' => $return_status
        ]);
        $return_id = $conn->lastInsertId();

        // Insert items into ReturnItems (assuming table exists)
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_sql = "INSERT INTO ReturnItems (return_id, product, quantity, unit_price, amount) 
                         VALUES (:return_id, :product, :quantity, :unit_price, :amount)";
            $item_stmt = $conn->prepare($item_sql);

            foreach ($_POST['items'] as $item) {
                $product = $item['product'] ?? '';
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = (float)($item['unit_price'] ?? 0.00);
                $amount = (float)($item['amount'] ?? 0.00);

                if (!empty($product) && $quantity > 0 && $unit_price >= 0) {
                    $item_stmt->execute([
                        ':return_id' => $return_id,
                        ':product' => $product,
                        ':quantity' => $quantity,
                        ':unit_price' => $unit_price,
                        ':amount' => $amount
                    ]);
                }
            }
        }

        $conn->commit();
        logAction($conn, $userId, "Return Created", "Return ID: $return_id created successfully");
        echo "<script>alert('Return created successfully!'); window.location.href = 'Returns.php';</script>";
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        logAction($conn, $userId, "Return Creation Failed", "Database error: " . $e->getMessage());
        echo "<script>alert('Database error saving return: " . addslashes($e->getMessage()) . "');</script>";
    } catch (Exception $e) {
        $conn->rollBack();
        logAction($conn, $userId, "Return Creation Failed", "Error: " . $e->getMessage());
        echo "<script>alert('Error saving return: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Return</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/NewCustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .item-table th, .item-table td { padding: 6px; vertical-align: middle; text-align: left; }
        .item-table select, .item-table input[type="number"] { width: 80%; margin: 0; padding: 4px; }
        .item-table .product { min-width: 200px; }
        .item-table .quantity { min-width: 80px; }
        .item-table .unit-price, .item-table .amount { min-width: 100px; }
        .item-table .btn-sm { padding: 2px 6px; }
        .table-container { overflow-x: hidden; width: 100%; }
        .container { overflow-x: hidden; }
        .left-sidebar { background-color: #343F79; }
        .left-sidebar .menu li a { color: #fff; }
        .total-section p, .total-section h5 { margin: 10px 0; }
        .total-value { margin-left: 5px; display: inline-block; width: 100px; }
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
        <li>
            <a href="Reports.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-file-invoice-dollar"></i><span> Reports</span>
            </a>
        </li>
        <li>
            <a href="logout.php" style="text-decoration: none; color: inherit;">
                <i class="fas fa-sign-out-alt"></i><span> Log out</span>
            </a>
        </li>
    </ul>
</div>

<div class="container">
    <h1>New Return<?php echo $prefill_order_id ? " Order #$prefill_order_id" : ($prefill_invoice_id ? " Invoice #$prefill_invoice_id" : ""); ?></h1>
    <form method="post" id="returnForm">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Customer Order</label>
                <select class="form-control" name="order_id" id="orderIdSelect" style="width: 400px; height: 40px;" required>
                    <option value="">Select Customer Order</option>
                    <?php foreach ($customer_orders as $order): ?>
                        <option value="<?php echo htmlspecialchars($order['OrderID']); ?>"
                            <?php echo $prefill_order_id == $order['OrderID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($order['CustomerName'] . " - Order #" . $order['OrderID'] . " (" . date('Y-m-d', strtotime($order['OrderDate'])) . ")"); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Return Reference</label>
                <input type="text" class="form-control" name="return_reference" style="width: 400px; height: 40px;" value="<?php echo htmlspecialchars($prefill_reference_number); ?>">
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Return Date</label>
                <input type="date" class="form-control" name="return_date" style="width: 400px; height: 40px;" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Return Status</label>
                <select class="form-control" name="return_status" style="width: 400px; height: 40px;" required>
                    <option value="Pending">Pending</option>
                    <option value="Processed">Processed</option>
                    <option value="Refunded">Refunded</option>
                </select>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Return Reason</label>
                <input type="text" class="form-control" name="return_reason" style="width: 400px; height: 40px;" placeholder="e.g., Defective, Wrong Item">
            </div>
        </div>
        <h4>Return Items</h4>
        <div class="table-container">
            <table class="table item-table" id="itemTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemTableBody">
                    <!-- Items will be dynamically added here -->
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-success mt-3" id="addItemBtn">Add Item</button>
        <div class="row">
            <div class="col-md-6" style="margin-left: 600px; margin-top: 20px;">
                <div class="total-section" style="width: 500px; height: 200px;">
                    <h5><strong>Total Refunded: </strong><span class="total-value" id="total">0.00</span></h5>
                    <input type="hidden" name="total" id="totalInput" value="0.00">
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" name="save" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
        </div>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const dropdown = btn.closest('.dropdown');
            dropdown.classList.toggle('active');
        });
    });

    const settingsIcon = document.getElementById('settingsIcon');
    const logoutMenu = document.getElementById('logoutMenu');
    settingsIcon.addEventListener('click', () => {
        logoutMenu.style.display = logoutMenu.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (event) => {
        if (!settingsIcon.contains(event.target) && !logoutMenu.contains(event.target)) {
            logoutMenu.style.display = 'none';
        }
    });

    document.getElementById('lightModeBtn').addEventListener('click', (e) => {
        e.preventDefault();
        document.body.classList.remove('dark-mode');
        logoutMenu.style.display = 'none';
    });
    document.getElementById('darkModeBtn').addEventListener('click', (e) => {
        e.preventDefault();
        document.body.classList.add('dark-mode');
        logoutMenu.style.display = 'none';
    });

    const itemTableBody = document.getElementById('itemTableBody');
    const addItemBtn = document.getElementById('addItemBtn');
    const totalSpan = document.getElementById('total');
    const totalInput = document.getElementById('totalInput');
    let rowId = 0;

    const products = <?php echo json_encode($products); ?>;
    let productOptions = '<option value="">Select Product</option>';
    products.forEach(product => {
        productOptions += `<option value="${product.product_name}" data-price="${product.price}">${product.product_name}</option>`;
    });

    const productPrices = {};
    products.forEach(product => {
        productPrices[product.product_name] = parseFloat(product.price);
    });

    // Prefill items from CustomerOrderItems if order_id is provided
    const prefillItems = <?php echo json_encode($prefill_items); ?>;
    if (prefillItems.length > 0) {
        prefillItems.forEach(item => {
            rowId++;
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td><select class="form-control product" name="items[${rowId}][product]" required>${productOptions}</select></td>
                <td><input type="number" class="form-control quantity" name="items[${rowId}][quantity]" min="1" max="${item.Quantity}" value="${item.Quantity}" required></td>
                <td><input type="number" class="form-control unit-price" name="items[${rowId}][unit_price]" min="0" step="0.01" value="${item.UnitPrice}" readonly></td>
                <td><input type="number" class="form-control amount" name="items[${rowId}][amount]" value="${item.Amount}" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            itemTableBody.appendChild(newRow);
            const productSelect = newRow.querySelector('.product');
            productSelect.value = item.ProductName;
            setupRowListeners(newRow);
        });
    }

    function updateTotal() {
        let total = 0;
        document.querySelectorAll('.amount').forEach(amountInput => {
            total += parseFloat(amountInput.value) || 0;
        });
        totalSpan.textContent = total.toFixed(2);
        totalInput.value = total.toFixed(2);
    }

    function setupRowListeners(row) {
        const productSelect = row.querySelector('.product');
        const quantityInput = row.querySelector('.quantity');
        const unitPriceInput = row.querySelector('.unit-price');
        const amountInput = row.querySelector('.amount');
        const removeBtn = row.querySelector('.remove-row');

        function calculateAmount() {
            const quantity = parseInt(quantityInput.value) || 0;
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const finalAmount = quantity * unitPrice;

            amountInput.value = finalAmount.toFixed(2);
            updateTotal();
        }

        productSelect.addEventListener('change', function() {
            const productName = this.value;
            const price = productPrices[productName] || 0;
            unitPriceInput.value = price.toFixed(2);
            calculateAmount();
        });
        quantityInput.addEventListener('input', calculateAmount);
        unitPriceInput.addEventListener('input', calculateAmount);
        removeBtn.addEventListener('click', function() {
            row.remove();
            updateTotal();
        });
    }

    addItemBtn.addEventListener('click', function() {
        rowId++;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td><select class="form-control product" name="items[${rowId}][product]" required>${productOptions}</select></td>
            <td><input type="number" class="form-control quantity" name="items[${rowId}][quantity]" min="1" required></td>
            <td><input type="number" class="form-control unit-price" name="items[${rowId}][unit_price]" min="0" step="0.01" readonly></td>
            <td><input type="number" class="form-control amount" name="items[${rowId}][amount]" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
        `;
        itemTableBody.appendChild(newRow);
        setupRowListeners(newRow);
    });

    document.getElementById('cancelBtn').addEventListener('click', function() {
        window.location.href = 'Returns.php';
    });

    updateTotal();
});
</script>
</body>
</html>