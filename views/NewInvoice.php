<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
if (!isset($_SESSION['last_invoice_log']) || (time() - $_SESSION['last_invoice_log']) > 300) {
    logAction($conn, $userId, "Accessed New Invoice Page", "User accessed the new invoice page");
    $_SESSION['last_invoice_log'] = time();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!isset($conn) || !$conn) {
    die("Database connection failed. Check database.php configuration.");
}

// Get order_id from URL if provided
$prefill_order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

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

// Fetch order items if order_id is provided
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
        $reference_number = $_POST['reference_number'] ?? '';
        $estimated_delivery_date = $_POST['estimated_delivery_date'] ?? '';
        $payment_status = $_POST['payment_status'] ?? 'Pending';
        $payment_type = $_POST['payment_type'] ?? 'Cash';
        $payment_terms = $_POST['payment_terms'] ?? 'COD';
        $total = (float)($_POST['total'] ?? 0.00);

        // Validation
        if (empty($order_id) || empty($estimated_delivery_date)) {
            throw new Exception("Customer Name and Estimated Delivery Date are required.");
        }

        // Fetch CustomerName based on OrderID
        $stmt = $conn->prepare("SELECT CustomerName FROM CustomerOrders WHERE OrderID = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $customer_name = $stmt->fetchColumn();
        if ($customer_name === false) {
            throw new Exception("Invalid Order ID selected.");
        }

        // Insert into Invoice table (only total_amount, no sub_total or discount_percent)
        $sql = "INSERT INTO Invoice (reference_number, payment_type, client_name, date, tin, payment_terms, total_amount, status) 
                VALUES (:reference_number, :payment_type, :client_name, :date, :tin, :payment_terms, :total_amount, :status)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':reference_number' => $reference_number,
            ':payment_type' => $payment_type,
            ':client_name' => $customer_name,
            ':date' => $estimated_delivery_date,
            ':tin' => "Order-$order_id",
            ':payment_terms' => $payment_terms,
            ':total_amount' => $total,
            ':status' => $payment_status
        ]);
        $invoice_id = $conn->lastInsertId();

        // Insert items into InvoiceItems
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_sql = "INSERT INTO InvoiceItems (invoice_id, product, quantity, unit_price, amount) 
                         VALUES (:invoice_id, :product, :quantity, :unit_price, :amount)";
            $item_stmt = $conn->prepare($item_sql);

            foreach ($_POST['items'] as $item) {
                $product = $item['product'] ?? '';
                $quantity = (int)($item['quantity'] ?? 0);
                $unit_price = (float)($item['unit_price'] ?? 0.00);
                $amount = (float)($item['amount'] ?? 0.00);

                if (!empty($product) && $quantity > 0 && $unit_price >= 0) {
                    $item_stmt->execute([
                        ':invoice_id' => $invoice_id,
                        ':product' => $product,
                        ':quantity' => $quantity,
                        ':unit_price' => $unit_price,
                        ':amount' => $amount
                    ]);
                }
            }
        }

        $conn->commit();
        logAction($conn, $userId, "Invoice Created", "Invoice ID: $invoice_id created successfully");
        echo "<script>alert('Invoice created successfully!'); window.location.href = 'Invoice.php';</script>";
        exit;
    } catch (PDOException $e) {
        $conn->rollBack();
        logAction($conn, $userId, "Invoice Creation Failed", "Database error: " . $e->getMessage());
        echo "<script>alert('Database error saving invoice: " . addslashes($e->getMessage()) . "');</script>";
    } catch (Exception $e) {
        $conn->rollBack();
        logAction($conn, $userId, "Invoice Creation Failed", "Error: " . $e->getMessage());
        echo "<script>alert('Error saving invoice: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Invoice</title>
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
        .percent-input-container { display: inline-flex; align-items: center; margin-left: 5px; }
        .percent-input-container input { width: 70px; margin-right: 5px; }
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
    <h1>New Invoice<?php echo $prefill_order_id ? " Order #$prefill_order_id" : ""; ?></h1>
    <form method="post" id="invoiceForm">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Customer Name</label>
                <select class="form-control" name="order_id" id="orderIdSelect" style="width: 400px; height: 40px;" required>
                    <option value="">Select Customer</option>
                    <?php foreach ($customer_orders as $order): ?>
                        <option value="<?php echo htmlspecialchars($order['OrderID']); ?>"
                            <?php echo $prefill_order_id == $order['OrderID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($order['CustomerName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Reference Number</label>
                <input type="text" class="form-control" name="reference_number" style="width: 400px; height: 40px;">
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Estimated Delivery Date</label>
                <input type="date" class="form-control" name="estimated_delivery_date" style="width: 400px; height: 40px;" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Payment Status</label>
                <select class="form-control" name="payment_status" style="width: 400px; height: 40px;" required>
                    <option value="Pending">Pending</option>
                    <option value="Paid">Paid</option>
                    <option value="Overdue">Overdue</option>
                </select>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Payment Type</label>
                <select class="form-control" name="payment_type" style="width: 400px; height: 40px;" required>
                    <option value="Cash">Cash</option>
                    <option value="Charge">Charge</option>
                </select>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Payment Terms</label>
                <select class="form-control" name="payment_terms" style="width: 400px; height: 40px;" required>
                    <option value="COD">COD (Cash on Delivery)</option>
                    <option value="Net 30">Net 30</option>
                    <option value="Net 60">Net 60</option>
                    <option value="12 Months">12 Months to Pay</option>
                    <option value="Due on Receipt">Due on Receipt</option>
                </select>
            </div>
        </div>
        <h4>Item Table</h4>
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
                    <p><strong>Sub Total: </strong><span class="total-value" id="subTotal">0.00</span></p>
                    <p><strong>Discount (%): </strong><span class="percent-input-container">
                        <input type="number" id="discountPercent" name="discount_percent" value="0" min="0" max="100" step="0.01">
                        <span>%</span>
                    </span></p>
                    <h5><strong>Total: </strong><span class="total-value" id="total">0.00</span></h5>
                    <input type="hidden" name="sub_total" id="subTotalInput" value="0.00">
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
    const subTotalSpan = document.getElementById('subTotal');
    const discountPercentInput = document.getElementById('discountPercent');
    const totalSpan = document.getElementById('total');
    const subTotalInput = document.getElementById('subTotalInput');
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
                <td><input type="number" class="form-control quantity" name="items[${rowId}][quantity]" min="1" value="${item.Quantity}" required></td>
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

    function updateTotals() {
        let subTotal = 0;
        document.querySelectorAll('.amount').forEach(amountInput => {
            subTotal += parseFloat(amountInput.value) || 0;
        });
        subTotalSpan.textContent = subTotal.toFixed(2);
        subTotalInput.value = subTotal.toFixed(2);

        const discountPercent = parseFloat(discountPercentInput.value) || 0;
        const discountAmount = (subTotal * discountPercent) / 100;
        const total = subTotal - discountAmount;

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
            updateTotals();
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
            updateTotals();
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

    discountPercentInput.addEventListener('input', updateTotals);

    document.getElementById('cancelBtn').addEventListener('click', function() {
        window.location.href = 'Invoice.php';
    });

    updateTotals();
});
</script>
</body>
</html>