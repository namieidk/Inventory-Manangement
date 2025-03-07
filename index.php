<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .table {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<form name="myform" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
    <select class="btn btn-outline-secondary" name="orderBy" onchange="document.myform.submit();">
        <option value="">Order By</option>
        <option value="name-asc" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'name-asc') echo 'selected'; ?>>Ascending (A → Z)</option>
        <option value="name-desc" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'name-desc') echo 'selected'; ?>>Descending (Z → A)</option>
        <option value="price-asc" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'price-asc') echo 'selected'; ?>>Low Price (Ascending)</option>
        <option value="price-desc" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'price-desc') echo 'selected'; ?>>High Price (Descending)</option>
        <option value="newest" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'newest') echo 'selected'; ?>>Newest</option>
        <option value="oldest" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'oldest') echo 'selected'; ?>>Oldest</option>
        <option value="best-seller" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'best-seller') echo 'selected'; ?>>Best Seller</option>
        <option value="active" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'active') echo 'selected'; ?>>Active</option>
        <option value="inactive" <?php if(isset($_POST['orderBy']) && $_POST['orderBy'] == 'inactive') echo 'selected'; ?>>Inactive</option>
    </select>
</form>

<table class="table table-striped table-hover">
    <thead>
        <tr>
            <th>Prod ID</th>
            <th>Product</th>
            <th>Type Name</th>
            <th>Supplier Name</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
    <?php
    include '../database/database.php';
    session_start();

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    echo "<pre>$sql</pre>";


    $sql = "SELECT id, product_name, product_type, supplier_name, price, stock, status, date_added, sales FROM sample_products WHERE 1=1";
    $order = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';

    // Sorting logic
    switch ($order) {
        case 'name-asc':
            $sql .= " ORDER BY product_name ASC";
            break;
        case 'name-desc':
            $sql .= " ORDER BY product_name DESC";
            break;
        case 'price-asc':
            $sql .= " ORDER BY price ASC";
            break;
        case 'price-desc':
            $sql .= " ORDER BY price DESC";
            break;
        case 'newest':
            $sql .= " ORDER BY date_added DESC";
            break;
        case 'oldest':
            $sql .= " ORDER BY date_added ASC";
            break;
        case 'best-seller':
            $sql .= " ORDER BY sales DESC";
            break;
        case 'active':
            $sql .= " AND status = 'active' ORDER BY id ASC";
            break;
        case 'inactive':
            $sql .= " AND status = 'inactive' ORDER BY id ASC";
            break;
        default:
            $sql .= " ORDER BY id ASC"; // Default sorting
    }

    $result = $conn->query($sql);

    if (!$result) {
        die("Query failed: " . $conn->error);
    }

    if ($result->num_rows > 0):
        while ($product = $result->fetch_assoc()):
    ?>
        <tr>
            <td><?= htmlspecialchars($product['id']) ?></td>
            <td><?= htmlspecialchars($product['product_name']) ?></td>
            <td><?= htmlspecialchars($product['product_type']) ?></td>
            <td><?= htmlspecialchars($product['supplier_name']) ?></td>
            <td>₱<?= number_format($product['price'], 2) ?></td>
            <td><?= htmlspecialchars($product['stock']) ?></td>
            <td><?= htmlspecialchars($product['status']) ?></td>
            <td>
                <div class="d-flex align-items-center">
                    <button class="btn btn-warning btn-sm me-1" data-product-id="<?= $product['id'] ?>" data-toggle="modal" data-target="#editProductModal">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-info btn-sm me-1" data-toggle="modal" data-target="#scannerModal">
                        <i class="fas fa-barcode"></i>
                    </button>
                </div>
            </td>
        </tr>
    <?php
        endwhile;
    else:
    ?>
        <tr><td colspan="8" class="text-center">No products found</td></tr>
    <?php endif; ?>
</tbody>

</table>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>