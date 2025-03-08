<?php
include '../database/database.php'; // Assumes this provides a PDO connection
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than X seconds ago
if (!isset($_SESSION['last_NewCustomerOrder_log']) || (time() - $_SESSION['last_NewCustomerOrder_log']) > 300) { // 300 seconds = 5 minutes
    logAction($conn, $userId, "Accessed New Customer Order Page", "User accessed the New Customers Order page");
    $_SESSION['last_NewCustomerOrder_log'] = time();
}
// Fetch products for the dropdown, including price and stock
try {
    $stmt = $conn->prepare("SELECT id, product_name, price, stock FROM products WHERE status = 'active' AND stock > 0");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}

// Fetch distinct contact persons (CustomerName) from CustomerOrders
try {
    $customer_stmt = $conn->prepare("SELECT DISTINCT CustomerName FROM CustomerOrders ORDER BY CustomerName ASC");
    $customer_stmt->execute();
    $contact_persons = $customer_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $contact_persons = [];
    echo "<script>alert('Error fetching contact persons: " . addslashes($e->getMessage()) . "');</script>";
}

// Set current date for default value
$current_date = date('Y-m-d');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    // Ensure PDO connection is available
    if (!isset($conn) || !$conn) {
        die("Database connection not established.");
    }

    try {
        // Start a transaction
        $conn->beginTransaction();

        // Main order details
        $customer_name = $_POST['supplier_name'] ?? '';
        $order_date = $_POST['date'] ?? $current_date;
        $tin = $_POST['tin'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';
        $payment_terms = $_POST['payment_terms'] ?? '';
        $sub_total = $_POST['sub_total'] ?? 0.00; // Hidden input from JS
        $discount_percent = $_POST['discount_percent'] ?? 0.00; // Percentage from form
        $tax_percent = $_POST['tax_percent'] ?? 0.00; // Percentage from form
        $total = $_POST['total'] ?? 0.00; // Hidden input from JS
        $document_path = '';

        // Handle file upload
        if (isset($_FILES['documents']) && $_FILES['documents']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_name = basename($_FILES['documents']['name']);
            $document_path = $upload_dir . time() . '_' . $file_name;
            if (!move_uploaded_file($_FILES['documents']['tmp_name'], $document_path)) {
                throw new Exception("Failed to upload document.");
            }
        }

        // Validate required fields
        if (empty($customer_name) || empty($order_date) || empty($delivery_date) || empty($payment_terms)) {
            throw new Exception("All required fields must be filled.");
        }

        // Insert into CustomerOrders (store percentages, not calculated amounts)
        $sql = "INSERT INTO CustomerOrders (CustomerName, OrderDate, TIN, DeliveryDate, PaymentTerms, SubTotal, Discount, Tax, Total, DocumentPath, Status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$customer_name, $order_date, $tin, $delivery_date, $payment_terms, $sub_total, $discount_percent, $tax_percent, $total, $document_path]);
        $order_id = $conn->lastInsertId();

        // Insert order items and subtract stock
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_sql = "INSERT INTO CustomerOrderItems (OrderID, ProductID, ProductName, Quantity, Rate, Amount) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            
            foreach ($_POST['items'] as $item) {
                $product_id = $item['product_id'] ?? '';
                $quantity = (int)($item['quantity'] ?? 0);
                $rate = (float)($item['rate'] ?? 0.00);
                $amount = (float)($item['amount'] ?? 0.00);

                if (!empty($product_id) && $quantity > 0 && $rate >= 0) {
                    $product_stmt = $conn->prepare("SELECT product_name, stock FROM products WHERE id = ? AND status = 'active'");
                    $product_stmt->execute([$product_id]);
                    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($product === false) {
                        throw new Exception("Product not found for ID: $product_id");
                    }

                    $product_name = $product['product_name'];
                    $available_stock = (int)$product['stock'];

                    if ($available_stock < $quantity) {
                        throw new Exception("Insufficient stock for Product ID: $product_id. Available: $available_stock, Requested: $quantity");
                    }

                    $stock_update_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stock_update_stmt->execute([$quantity, $product_id, $quantity]);

                    if ($stock_update_stmt->rowCount() === 0) {
                        throw new Exception("Failed to update stock for Product ID: $product_id. Stock may have been modified concurrently.");
                    }

                    $item_stmt->execute([$order_id, $product_id, $product_name, $quantity, $rate, $amount]);
                }
            }
        }

        $conn->commit();
        echo "<script>alert('Order saved successfully! Stock updated.'); window.location.href = 'CustomerOrder.php';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('Error saving order: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Customer Order</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/NewCustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        .item-table th {
            padding: 4px;
            vertical-align: middle;
            text-align: left;
        }
        .item-table td {
            padding: 6px;
            vertical-align: middle;
            text-align: left;
        }
        .item-table select,
        .item-table input[type="number"] {
            width: 80%;
            margin: 0;
            padding: 4px;
        }
        .item-table .product-id {
            min-width: 200px;
        }
        .item-table .quantity {
            min-width: 80px;
        }
        .item-table .rate,
        .item-table .amount {
            min-width: 150px;
        }
        .item-table .btn-sm {
            padding: 2px 6px;
        }
        .table-container {
            overflow-x: hidden;
            width: 100%;
        }
        .container {
            overflow-x: hidden;
        }
        .left-sidebar {
            background-color: #343F79;
        }
        .left-sidebar .menu li a {
            color: #fff;
        }
        .total-section p, .total-section h5 {
            margin: 10px 0;
        }
        .total-value {
            margin-left: 5px;
            display: inline-block;
            width: 100px;
        }
        .percent-input-container {
            display: inline-flex;
            align-items: center;
            margin-left: 5px;
        }
        .percent-input-container input {
            width: 70px;
            margin-right: 5px;
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
            <li>
                <a href="logout.php" style="text-decoration: none; color: inherit;">
                    <i class="fas fa-sign-out-alt"></i><span> Log out</span>
                </a>
            </li>
    </ul>
</div>
<div class="container">
    <h1>New Customer Order</h1>
    <form method="post" id="supplierOrderForm" enctype="multipart/form-data">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Contact Person</label>
                <select class="form-control" name="supplier_name" style="width: 400px; height: 40px;" required>
                    <option value="">Select Contact Person</option>
                    <?php foreach ($contact_persons as $contact): ?>
                        <option value="<?php echo htmlspecialchars($contact); ?>"><?php echo htmlspecialchars($contact); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" style="width: 400px; height: 40px;" value="<?php echo $current_date; ?>" readonly required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">TIN</label>
                <input type="text" class="form-control" name="tin" style="width: 400px; height: 40px;">
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Delivery Date</label>
                <input type="date" class="form-control" name="delivery_date" style="width: 400px; height: 40px;" min="<?php echo $current_date; ?>" required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Payment Terms</label>
                <select class="form-control" name="payment_terms" style="width: 400px; height: 40px;" required>
                    <option value="">Select Payment Terms</option>
                    <option value="Cash on Delivery">Cash on Delivery</option>
                    <option value="Net 30">Net 30</option>
                    <option value="Net 60">Net 60</option>
                    <option value="Prepaid">Prepaid</option>
                </select>
            </div>
        </div>
        <h4>Item Table</h4>
        <div class="table-container">
            <table class="table item-table" id="itemTable">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th style="margin-left: -10px;">Quantity</th>
                        <th>Rate</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemTableBody">
                    <!-- Table rows will be dynamically added here -->
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-success mt-3" id="addOrderBtn">Add Order</button>
        <div class="row">
            <div class="col-md-6">
                <label class="form-label" style="margin-top: 50px;">Documents</label>
                <input type="file" class="form-control" name="documents" style="width: 400px;">
            </div>
            <div class="col-md-6" style="margin-left: 600px; margin-top: -60px;">
                <div class="total-section" style="width: 500px; height: 200px;">
                    <p><strong>Sub Total: </strong><span class="total-value" id="subTotal">0.00</span></p>
                    <p><strong>Discount (%): </strong><span class="percent-input-container">
                        <input type="number" id="discountPercent" name="discount_percent" value="0" min="0" max="100" step="0.01">
                        <span>%</span>
                    </span></p>
                    <p><strong>Tax (%): </strong><span class="percent-input-container">
                        <input type="number" id="taxPercent" name="tax_percent" value="0" min="0" max="100" step="0.01">
                        <span>%</span>
                    </span></p>
                    <h5><strong>Total: </strong><span class="total-value" id="total">0.00</span></h5>
                    <input type="hidden" name="sub_total" id="subTotalInput">
                    <input type="hidden" name="total" id="totalInput">
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" name="save" class="btn btn-primary">Save</button>
            <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const dropdown = btn.closest('.dropdown');
            dropdown.classList.toggle('active');
        });
    });

    const itemTableBody = document.getElementById('itemTableBody');
    const addOrderBtn = document.getElementById('addOrderBtn');
    const subTotalSpan = document.getElementById('subTotal');
    const discountPercentInput = document.getElementById('discountPercent');
    const taxPercentInput = document.getElementById('taxPercent');
    const totalSpan = document.getElementById('total');
    const subTotalInput = document.getElementById('subTotalInput');
    const totalInput = document.getElementById('totalInput');
    let rowCount = 0;

    const products = <?php echo json_encode($products); ?>;
    let productOptions = '<option value="">Select Product</option>';
    products.forEach(product => {
        productOptions += `<option value="${product.id}" data-price="${product.price}" data-stock="${product.stock}">${product.product_name}</option>`;
    });

    const productPrices = {};
    const productStocks = {};
    products.forEach(product => {
        productPrices[product.id] = parseFloat(product.price);
        productStocks[product.id] = parseInt(product.stock);
    });

    function updateTotals() {
        let subTotal = 0;
        document.querySelectorAll('.amount').forEach(amountInput => {
            subTotal += parseFloat(amountInput.value) || 0;
        });
        subTotalSpan.textContent = subTotal.toFixed(2);
        subTotalInput.value = subTotal.toFixed(2);

        const discountPercent = parseFloat(discountPercentInput.value) || 0;
        const discountAmount = (subTotal * discountPercent) / 100;
        const afterDiscount = subTotal - discountAmount;

        const taxPercent = parseFloat(taxPercentInput.value) || 0;
        const taxAmount = (afterDiscount * taxPercent) / 100;

        const total = afterDiscount + taxAmount;
        totalSpan.textContent = total.toFixed(2);
        totalInput.value = total.toFixed(2);
    }

    addOrderBtn.addEventListener('click', function() {
        rowCount++;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
            <td>
                <select class="form-control product-id" name="items[${rowCount}][product_id]" required>
                    ${productOptions}
                </select>
            </td>
            <td><input type="number" class="form-control quantity" name="items[${rowCount}][quantity]" min="1" required></td>
            <td><input type="number" class="form-control rate" name="items[${rowCount}][rate]" style="width: 70px;" min="0" step="0.01" readonly></td>
            <td><input type="number" class="form-control amount" name="items[${rowCount}][amount]" style="width: 70px;" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
        `;
        itemTableBody.appendChild(newRow);

        const productSelect = newRow.querySelector('.product-id');
        const quantityInput = newRow.querySelector('.quantity');
        const rateInput = newRow.querySelector('.rate');
        const amountInput = newRow.querySelector('.amount');
        const removeBtn = newRow.querySelector('.remove-row');

        function calculateAmount() {
            const quantity = parseInt(quantityInput.value) || 0;
            const rate = parseFloat(rateInput.value) || 0;
            const amount = quantity * rate;
            amountInput.value = amount.toFixed(2);
            updateTotals();
        }

        productSelect.addEventListener('change', function() {
            const productId = this.value;
            const price = productPrices[productId] || 0;
            const stock = productStocks[productId] || 0;
            rateInput.value = price.toFixed(2);
            quantityInput.max = stock;
            quantityInput.title = `Max available: ${stock}`;
            calculateAmount();
        });

        quantityInput.addEventListener('input', function() {
            const productId = productSelect.value;
            const stock = productStocks[productId] || 0;
            if (parseInt(this.value) > stock) {
                this.value = stock;
                alert(`Quantity cannot exceed available stock (${stock}) for this product.`);
            }
            calculateAmount();
        });

        removeBtn.addEventListener('click', function() {
            newRow.remove();
            updateTotals();
        });
    });

    discountPercentInput.addEventListener('input', updateTotals);
    taxPercentInput.addEventListener('input', updateTotals);

    document.getElementById('cancelBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            document.getElementById('supplierOrderForm').reset();
            itemTableBody.innerHTML = '';
            subTotalSpan.textContent = '0.00';
            discountPercentInput.value = '0';
            taxPercentInput.value = '0';
            totalSpan.textContent = '0.00';
            subTotalInput.value = '0.00';
            totalInput.value = '0.00';
        }
    });
});
</script>
</body>
</html>