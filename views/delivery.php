<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than 5 minutes ago
if (!isset($_SESSION['last_delivery_log']) || (time() - $_SESSION['last_delivery_log']) > 300) {
    logAction($conn, $userId, "Accessed delivery Page", "User accessed the delivery page");
    $_SESSION['last_delivery_log'] = time();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the order_id from the URL
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;

if (!$order_id) {
    die("No order ID provided.");
}

// Fetch products for reference (but we'll prioritize SupplierOrderItems)
try {
    $stmt = $conn->prepare("SELECT id, product_name, price FROM products WHERE status = 'active'");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}

// Fetch suppliers
try {
    $supplier_stmt = $conn->prepare("SELECT name FROM suppliers ORDER BY name ASC");
    $supplier_stmt->execute();
    $suppliers = $supplier_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    try {
        $supplier_stmt = $conn->prepare("SELECT DISTINCT SupplierName FROM SupplierOrders WHERE SupplierName IS NOT NULL AND SupplierName != '' ORDER BY SupplierName ASC");
        $supplier_stmt->execute();
        $suppliers = $supplier_stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $suppliers = [];
        echo "<script>alert('Error fetching suppliers: " . addslashes($e->getMessage()) . "');</script>";
    }
}

// Fetch supplier order items for the given order_id
try {
    $supplier_items_stmt = $conn->prepare("
        SELECT ProductName, Quantity, Rate, Amount
        FROM SupplierOrderItems
        WHERE OrderID = :order_id
    ");
    $supplier_items_stmt->execute([':order_id' => $order_id]);
    $supplier_order_items = $supplier_items_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($supplier_order_items)) {
        die("No items found in Supplier Order for OrderID: " . htmlspecialchars($order_id));
    }
} catch (PDOException $e) {
    die("Error fetching supplier order items: " . $e->getMessage());
}

// Fetch existing delivery data or supplier order data as fallback
try {
    $stmt = $conn->prepare("
        SELECT DeliveryID, SupplierName, OrderDate, TIN, DeliveryDate, PaymentTerms, SubTotal, Discount, Total, Status
        FROM Deliveries
        WHERE OrderID = :order_id
    ");
    $stmt->execute([':order_id' => $order_id]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        $stmt = $conn->prepare("
            SELECT SupplierName, OrderDate, TIN, DeliveryDate, PaymentTerms, SubTotal, Discount, Total, Status
            FROM SupplierOrders
            WHERE OrderID = :order_id
        ");
        $stmt->execute([':order_id' => $order_id]);
        $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$delivery) {
            die("Order not found in SupplierOrders.");
        }
    }

    $delivery_id = $delivery['DeliveryID'] ?? null;
    if ($delivery_id) {
        $stmt = $conn->prepare("
            SELECT ProductName, Quantity, Rate, Amount
            FROM DeliveryItems
            WHERE DeliveryID = :delivery_id
        ");
        $stmt->execute([':delivery_id' => $delivery_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If no delivery exists yet, use supplier order items as the initial items
        $items = $supplier_order_items;
    }
} catch (PDOException $e) {
    die("Error fetching delivery/order details: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    if (!isset($conn) || !$conn) {
        die("Database connection not established.");
    }

    try {
        $conn->beginTransaction();

        $supplier_name = $_POST['supplier_name'] ?? '';
        $order_date = $_POST['date'] ?? '';
        $tin = $_POST['tin'] ?? '';
        $delivery_date = $_POST['delivery_date'] ?? '';
        $payment_terms = $_POST['payment_terms'] ?? '';
        $sub_total = $_POST['sub_total'] ?? 0.00;
        $discount_percent = $_POST['discount_percent'] ?? 0.00;
        $total = $_POST['total'] ?? 0.00;
        $status = $_POST['status'] ?? 'Pending';

        if (empty($supplier_name) || empty($order_date) || empty($delivery_date) || empty($payment_terms) || empty($status)) {
            throw new Exception("All required fields must be filled.");
        }

        if ($delivery_id) {
            $sql = "UPDATE Deliveries 
                    SET SupplierName = ?, OrderDate = ?, TIN = ?, DeliveryDate = ?, PaymentTerms = ?, SubTotal = ?, Discount = ?, Total = ?, Status = ?
                    WHERE DeliveryID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$supplier_name, $order_date, $tin, $delivery_date, $payment_terms, $sub_total, $discount_percent, $total, $status, $delivery_id]);
            $conn->prepare("DELETE FROM DeliveryItems WHERE DeliveryID = ?")->execute([$delivery_id]);
        } else {
            $sql = "INSERT INTO Deliveries (OrderID, SupplierName, OrderDate, TIN, DeliveryDate, PaymentTerms, SubTotal, Discount, Total, Status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$order_id, $supplier_name, $order_date, $tin, $delivery_date, $payment_terms, $sub_total, $discount_percent, $total, $status]);
            $delivery_id = $conn->lastInsertId();
        }

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            $item_sql = "INSERT INTO DeliveryItems (DeliveryID, ProductName, Quantity, Rate, Amount) 
                         VALUES (?, ?, ?, ?, ?)";
            $item_stmt = $conn->prepare($item_sql);
            
            foreach ($_POST['items'] as $item) {
                $product_name = $item['product_name'] ?? '';
                $quantity = (int)($item['quantity'] ?? 0);
                $rate = (float)($item['rate'] ?? 0.00);
                $amount = (float)($item['amount'] ?? 0.00);

                if (!empty($product_name) && $quantity > 0 && $rate >= 0) {
                    $item_stmt->execute([$delivery_id, $product_name, $quantity, $rate, $amount]);
                }
            }
        }

        $conn->commit();
        echo "<script>alert('Delivery updated successfully!'); window.location.href = 'deliverytable.php';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('Error updating delivery: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Delivery - Order #<?php echo htmlspecialchars($order_id); ?></title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/NewCustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .item-table th, .item-table td {
            padding: 6px;
            vertical-align: middle;
            text-align: left;
        }
        .item-table select, .item-table input[type="number"] {
            width: 100%;
            padding: 4px;
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

<div class="container">
    <h1>Edit Delivery - Order #<?php echo htmlspecialchars($order_id); ?></h1>
    <form method="post" id="deliveryForm">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Supplier Name</label>
                <select class="form-control" name="supplier_name" style="width: 400px; height: 40px;" required>
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo htmlspecialchars($supplier); ?>" <?php echo $supplier === $delivery['SupplierName'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($supplier); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Order Date</label>
                <input type="date" class="form-control" name="date" style="width: 400px; height: 40px;" value="<?php echo htmlspecialchars($delivery['OrderDate']); ?>" required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Payment Terms</label>
                <select class="form-control" name="payment_terms" style="width: 400px; height: 40px;" required>
                    <option value="">Select Payment Terms</option>
                    <option value="Cash on Delivery" <?php echo $delivery['PaymentTerms'] === 'Cash on Delivery' ? 'selected' : ''; ?>>Cash on Delivery</option>
                    <option value="Net 30" <?php echo $delivery['PaymentTerms'] === 'Net 30' ? 'selected' : ''; ?>>Net 30</option>
                    <option value="Net 60" <?php echo $delivery['PaymentTerms'] === 'Net 60' ? 'selected' : ''; ?>>Net 60</option>
                    <option value="Prepaid" <?php echo $delivery['PaymentTerms'] === 'Prepaid' ? 'selected' : ''; ?>>Prepaid</option>
                </select>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Delivery Date</label>
                <input type="date" class="form-control" name="delivery_date" style="width: 400px; height: 40px;" value="<?php echo htmlspecialchars($delivery['DeliveryDate']); ?>" required>
            </div>
            <div class="col-md-6 mt-3">
                <label class="form-label">Status</label>
                <select class="form-control" name="status" style="width: 400px; height: 40px;" required>
                    <option value="Pending" <?php echo $delivery['Status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Delivered" <?php echo $delivery['Status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="Cancelled" <?php echo $delivery['Status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="On Hold" <?php echo $delivery['Status'] === 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                </select>
            </div>
        </div>
        <h4>Item Table</h4>
        <div class="table-container">
            <table class="table item-table" id="itemTable">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="itemTableBody">
                    <!-- Table rows will be dynamically populated -->
                </tbody>
            </table>
        </div>
        <button type="button" class="btn btn-success mt-3" id="addOrderBtn">Add Item</button>
        <div class="row">
            <div class="col-md-6" style="margin-left: 600px; margin-top: -60px;">
                <div class="total-section" style="width: 500px; height: 200px;">
                    <p><strong>Sub Total: </strong><span class="total-value" id="subTotal"><?php echo number_format($delivery['SubTotal'], 2); ?></span></p>
                    <p><strong>Discount (%): </strong><span class="percent-input-container">
                        <input type="number" id="discountPercent" name="discount_percent" value="<?php echo htmlspecialchars($delivery['Discount']); ?>" min="0" max="100" step="0.01">
                        <span>%</span>
                    </span></p>
                    <h5><strong>Total: </strong><span class="total-value" id="total"><?php echo number_format($delivery['Total'], 2); ?></span></h5>
                    <input type="hidden" name="sub_total" id="subTotalInput" value="<?php echo htmlspecialchars($delivery['SubTotal']); ?>">
                    <input type="hidden" name="total" id="totalInput" value="<?php echo htmlspecialchars($delivery['Total']); ?>">
                </div>
            </div>
        </div>
        <div class="mt-3">
            <button type="submit" name="save" class="btn btn-primary">Update Delivery</button>
            <button type="button" class="btn btn-secondary" id="cancelBtn">Back</button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const dropdown = btn.closest('.dropdown');
            dropdown.classList.toggle('active');
        });
    });

    // Element references
    const itemTableBody = document.getElementById('itemTableBody');
    const addOrderBtn = document.getElementById('addOrderBtn');
    const subTotalSpan = document.getElementById('subTotal');
    const discountPercentInput = document.getElementById('discountPercent');
    const totalSpan = document.getElementById('total');
    const subTotalInput = document.getElementById('subTotalInput');
    const totalInput = document.getElementById('totalInput');
    let rowId = 0;

    // Product data
    const products = <?php echo json_encode($products); ?>;
    const existingItems = <?php echo json_encode($items); ?>;
    const supplierOrderItems = <?php echo json_encode($supplier_order_items); ?>;

    // Generate product options limited to supplier order items
    let productOptions = '';
    supplierOrderItems.forEach(item => {
        const price = products.find(p => p.product_name === item.ProductName)?.price || item.Rate;
        productOptions += `<option value="${item.ProductName}" data-price="${price}">${item.ProductName}</option>`;
    });

    const productPrices = {};
    supplierOrderItems.forEach(item => {
        productPrices[item.ProductName] = parseFloat(products.find(p => p.product_name === item.ProductName)?.price || item.Rate);
    });

    // Function to calculate totals
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

    // Function to set up row event listeners
    function setupRowListeners(row) {
        const productSelect = row.querySelector('.product-name');
        const quantityInput = row.querySelector('.quantity');
        const rateInput = row.querySelector('.rate');
        const amountInput = row.querySelector('.amount');
        const removeBtn = row.querySelector('.remove-row');

        function calculateAmount() {
            const quantity = parseInt(quantityInput.value) || 0;
            const rate = parseFloat(rateInput.value) || 0;
            const amount = quantity * rate;
            amountInput.value = amount.toFixed(2);
            updateTotals();
        }

        productSelect.addEventListener('change', function() {
            const productName = this.value;
            const price = productPrices[productName] || 0;
            rateInput.value = price.toFixed(2);
            calculateAmount();
        });

        quantityInput.addEventListener('input', calculateAmount);
        removeBtn.addEventListener('click', function() {
            row.remove();
            updateTotals();
        });
    }

    // Populate item table with all supplier order items initially (or delivery items if they exist)
    existingItems.forEach((item, index) => {
        rowId++;
        const newRow = document.createElement('tr');
        const productOptionExists = supplierOrderItems.some(p => p.ProductName === item.ProductName);
        const selectedOption = productOptionExists 
            ? `<option value="${item.ProductName}" data-price="${productPrices[item.ProductName] || 0}" selected>${item.ProductName}</option>`
            : `<option value="${item.ProductName}" selected>${item.ProductName} (Not in Supplier Order)</option>`;

        newRow.innerHTML = `
            <td>
                <select class="form-control product-name" name="items[${rowId}][product_name]" required>
                    ${productOptions}
                    ${!productOptionExists ? selectedOption : ''}
                </select>
            </td>
            <td><input type="number" class="form-control quantity" name="items[${rowId}][quantity]" min="1" value="${item.Quantity}" required></td>
            <td><input type="number" class="form-control rate" name="items[${rowId}][rate]" min="0" step="0.01" value="${item.Rate}" readonly></td>
            <td><input type="number" class="form-control amount" name="items[${rowId}][amount]" value="${item.Amount}" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
        `;
        itemTableBody.appendChild(newRow);
        setupRowListeners(newRow);
    });

    // Add new item functionality with next available product from SupplierOrderItems
    let supplierItemIndex = 0;
    function addNewItem() {
        try {
            if (supplierOrderItems.length === 0) {
                console.error('No items found in Supplier Order Details for this order');
                return;
            }

            rowId++;
            // Get the next supplier order item
            const defaultSupplierItem = supplierOrderItems[supplierItemIndex % supplierOrderItems.length];
            const defaultPrice = productPrices[defaultSupplierItem.ProductName] || defaultSupplierItem.Rate;

            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <select class="form-control product-name" name="items[${rowId}][product_name]" required>
                        ${productOptions}
                    </select>
                </td>
                <td><input type="number" class="form-control quantity" name="items[${rowId}][quantity]" min="1" value="${defaultSupplierItem.Quantity}" required></td>
                <td><input type="number" class="form-control rate" name="items[${rowId}][rate]" min="0" step="0.01" value="${defaultSupplierItem.Rate}" readonly></td>
                <td><input type="number" class="form-control amount" name="items[${rowId}][amount]" value="${defaultSupplierItem.Amount}" readonly></td>
                <td><button type="button" class="btn btn-danger btn-sm remove-row">Remove</button></td>
            `;
            itemTableBody.appendChild(newRow);

            // Set the default product and trigger change event
            const productSelect = newRow.querySelector('.product-name');
            productSelect.value = defaultSupplierItem.ProductName;
            setupRowListeners(newRow);
            productSelect.dispatchEvent(new Event('change')); // Trigger change to update rate
            supplierItemIndex++; // Move to next item
        } catch (error) {
            console.error('Error adding new item:', error);
        }
    }

    // Add event listener to Add Item button
    if (addOrderBtn) {
        addOrderBtn.addEventListener('click', addNewItem);
    } else {
        console.error('Add Item button not found');
    }

    // Discount change handler
    discountPercentInput.addEventListener('input', updateTotals);

    // Cancel button handler
    document.getElementById('cancelBtn').addEventListener('click', function() {
        window.location.href = 'SupplierOrder.php';
    });

    // Initial totals calculation
    updateTotals();
});
</script>
</body>
</html>