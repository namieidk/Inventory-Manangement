<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than X seconds ago
if (!isset($_SESSION['last_new_product_log']) || (time() - $_SESSION['last_new_product_log']) > 300) { // 300 seconds = 5 minutes
    logAction($conn, $userId, "Accessed New Product Page", "User accessed the new product page");
    $_SESSION['last_new_product_log'] = time();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fetch suppliers (CompanyName) for the dropdown
try {
    $supplier_stmt = $conn->prepare("SELECT DISTINCT CompanyName FROM supplier ORDER BY CompanyName ASC");
    $supplier_stmt->execute();
    $suppliers = $supplier_stmt->fetchAll(PDO::FETCH_ASSOC);
    $supplier_names = array_column($suppliers, 'CompanyName');
} catch (PDOException $e) {
    $suppliers = [];
    $supplier_names = [];
    echo "<script>alert('Error fetching suppliers: " . addslashes($e->getMessage()) . "');</script>";
}

// Fetch product types from ProductType table for the dropdown
try {
    $product_type_stmt = $conn->prepare("SELECT product_type FROM ProductType ORDER BY product_type ASC");
    $product_type_stmt->execute();
    $product_types = $product_type_stmt->fetchAll(PDO::FETCH_ASSOC);
    $product_type_names = array_column($product_types, 'product_type');
} catch (PDOException $e) {
    $product_types = [];
    $product_type_names = [];
    echo "<script>alert('Error fetching product types: " . addslashes($e->getMessage()) . "');</script>";
}

// Handle form submission for adding a new product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    if (!isset($conn) || !$conn) {
        die("Database connection not established.");
    }

    try {
        $conn->beginTransaction();

        $product_name = $_POST['product_name'] ?? '';
        $product_type = $_POST['product_type'] ?? '';
        $supplier_name = $_POST['supplier_name'] ?? ''; // This will now be CompanyName
        $price = floatval($_POST['price'] ?? 0.00);
        $stock = intval($_POST['stock'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        $serial_number = $_POST['serial_number'] ?? '';

        if (empty($product_name) || empty($product_type) || empty($supplier_name) || $price < 0) {
            throw new Exception("All required fields must be filled, and price must be non-negative.");
        }

        // Insert new product into products table (usersId removed)
        $sql = "INSERT INTO products (product_name, product_type, supplier_name, price, stock, status, serial_number) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$product_name, $product_type, $supplier_name, $price, $stock, $status, $serial_number]);

        $conn->commit();
        echo "<script>alert('Product added successfully!'); window.location.href = 'Inventory.php';</script>";
        exit;
    } catch (Exception $e) {
        $conn->rollBack();
        echo "<script>alert('Error adding product: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product to Inventory</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/NewCustomerOrder.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .container {
            overflow-x: hidden;
            padding: 20px;
        }
        .left-sidebar {
            background-color: #343F79;
        }
        .left-sidebar .menu li a {
            color: #fff;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-label {
            width: 150px;
            display: inline-block;
        }
        .form-control {
            width: 400px;
            height: 40px;
            display: inline-block;
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
    <h1>Add New Product to Inventory</h1>
    <form method="post" id="newProductForm">
        <div class="form-group">
            <label class="form-label">Product Name</label>
            <input type="text" class="form-control" name="product_name" required>
        </div>
        <div class="form-group">
            <label class="form-label">Product Type</label>
            <select class="form-control" name="product_type" required>
                <option value="">Select Product Type</option>
                <?php foreach ($product_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type['product_type']); ?>">
                        <?php echo htmlspecialchars($type['product_type']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Company Name</label> <!-- Changed from Supplier Name -->
            <select class="form-control" name="supplier_name" required>
                <option value="">Select Company</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?php echo htmlspecialchars($supplier['CompanyName']); ?>">
                        <?php echo htmlspecialchars($supplier['CompanyName']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Serial Number</label>
            <input type="text" class="form-control" name="serial_number" placeholder="Enter serial number">
        </div>
        <div class="form-group">
            <label class="form-label">Price</label>
            <input type="number" class="form-control" name="price" step="0.01" min="0" required>
        </div>
        <div class="form-group">
            <label class="form-label">Stock</label>
            <input type="number" class="form-control" name="stock" value="0" min="0">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select class="form-control" name="status" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="mt-3">
            <button type="submit" name="save" class="btn btn-primary">Add Product</button>
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

    document.getElementById('cancelBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to cancel? All unsaved changes will be lost.')) {
            window.location.href = 'Inventory.php';
        }
    });
});
</script>
</body>
</html>