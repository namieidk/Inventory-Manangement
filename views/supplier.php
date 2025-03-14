<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than X seconds ago
if (!isset($_SESSION['last_Supplier_log']) || (time() - $_SESSION['last_Supplier_log']) > 300) { // 300 seconds = 5 minutes
    logAction($conn, $userId, "Accessed Supplier Page", "User accessed the Supplier page");
    $_SESSION['last_Supplier_log'] = time();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define $orderBy globally with default empty value
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : (isset($_GET['orderBy']) ? $_GET['orderBy'] : '');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['supplier_id'])) {
        $supplier_id = $_POST['supplier_id'];
        $company_name = $_POST['company_name'];  
        $phone = $_POST['phone'];
        $contact_person = $_POST['contact_person'];
        $contact_phone = $_POST['contact_phone'];
        $email = $_POST['email'];
        $payment_terms = $_POST['payment_terms'];
        $address1 = $_POST['address1'];
        $address2 = $_POST['address2'];
        $remarks = $_POST['remarks'];
        $stmt = $conn->prepare("UPDATE Supplier SET CompanyName = ?, Phone = ?, ContactPerson = ?, ContactPhone = ?, Email = ?, PaymentTerms = ?, Address1 = ?, Address2 = ?, Remarks = ? WHERE SupplierID = ?");  
        $stmt->execute([$company_name, $phone, $contact_person, $contact_phone, $email, $payment_terms, $address1, $address2, $remarks, $supplier_id]);
        echo 'success';
        exit;
    } elseif (isset($_POST['companyName'])) {  
        $companyName = $_POST['companyName'];  
        $phone = $_POST['phone'];
        $contactPerson = $_POST['contactPerson'];
        $contactPhone = $_POST['contactPhone'];
        $email = $_POST['email'];
        $paymentTerms = $_POST['paymentTerms'];
        $address1 = $_POST['address1'];
        $address2 = $_POST['address2'];
        $remarks = $_POST['remarks'];
        $userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : 1;
        $stmt = $conn->prepare("INSERT INTO Supplier (CompanyName, Phone, ContactPerson, ContactPhone, Email, PaymentTerms, Address1, Address2, Remarks, UserId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");  
        $stmt->execute([$companyName, $phone, $contactPerson, $contactPhone, $email, $paymentTerms, $address1, $address2, $remarks, $userId]);
        echo 'success';
        exit;
    }
}

// Handle AJAX request for fetching supplier details
if (isset($_GET['supplier_id'])) {
    $supplier_id = $_GET['supplier_id'];
    $stmt = $conn->prepare("SELECT * FROM Supplier WHERE SupplierID = ?");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $supplier ? json_encode($supplier) : json_encode(['error' => 'Supplier not found']);
    exit;
}

// Unified fetch function for suppliers (removed filterBy)
function fetchSuppliers($conn, $searchTerm = '', $orderBy = '') {
    try {
        $sql = "SELECT * FROM Supplier WHERE 1=1";
        $params = [];
        $orderClause = " ORDER BY SupplierID DESC"; // Default order

        // Search functionality
        if (!empty($searchTerm)) {
            $sql .= " AND (CompanyName LIKE :search 
                        OR Phone LIKE :search 
                        OR ContactPerson LIKE :search 
                        OR Email LIKE :search 
                        OR SupplierID LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Order logic
        switch ($orderBy) {
            case 'name-asc':
                $orderClause = " ORDER BY CompanyName ASC";
                break;
            case 'name-desc':
                $orderClause = " ORDER BY CompanyName DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY SupplierID DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY SupplierID ASC";
                break;
            default:
                break;
        }

        $sql .= $orderClause;

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

// Handle AJAX search request (removed filterBy)
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
    $suppliers = fetchSuppliers($conn, $searchTerm, $orderBy);
    header('Content-Type: application/json');
    echo json_encode($suppliers);
    exit;
}

// Initial page load
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$suppliers = fetchSuppliers($conn, $searchTerm, $orderBy);
if (isset($suppliers['error'])) {
    $error = $suppliers['error'];
    $suppliers = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/supplier.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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
            margin-left: 0;
            display: flex;
            flex-direction: column;
            width: 100%;
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
            justify-content: flex-start;
            width: 100%;
        }
        .controls-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 15px;
            width: 100%;
        }
        .search-container {
            position: relative;
            width: 300px;
            margin-right: 780px;
            margin-left: 40px;
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
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-left: auto;
        }
        .table {
            width: 100%;
            table-layout: auto;
        }
        .table th, .table td {
            white-space: nowrap;
            padding: 8px;
        }
        .modal-lg-custom {
            max-width: 900px;
        }
        select.btn.btn-outline-secondary {
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
        <h1>Supplier</h1>
    </div>

    <div class="controls-container">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <button class="btn btn-dark mr-2" id="newProductBtn" style="margin-right: 10px;">New <i class="fa fa-plus"></i></button>
        <form name="orderForm" id="orderForm" method="post" class="d-flex align-items-center">
            <select class="btn btn-outline-secondary mr-2" style="margin-right: 10px;" name="orderBy" id="orderBySelect" onchange="updateTable()">
                <option value="">Order By</option>
                <option value="name-asc" <?php if ($orderBy === 'name-asc') echo 'selected'; ?>>Ascending (A → Z)</option>
                <option value="name-desc" <?php if ($orderBy === 'name-desc') echo 'selected'; ?>>Descending (Z → A)</option>
                <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
                <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
            </select>
        </form>
    </div>

    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Supplier ID</th>
                    <th>Company Name</th>  
                    <th>Phone</th>
                    <th>Contact Person</th>
                    <th>Contact Phone</th>
                    <th>Email</th>
                    <th>Payment Terms</th>
                    <th>Address 1</th>
                    <th>Address 2</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="supplierTableBody">
                <?php if (isset($error)): ?>
                    <tr><td colspan="11" class="text-center text-danger"><?= htmlspecialchars($error) ?></td></tr>
                <?php elseif (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><?= htmlspecialchars($supplier['SupplierID']) ?></td>
                            <td><?= htmlspecialchars($supplier['CompanyName']) ?></td>  
                            <td><?= htmlspecialchars($supplier['Phone']) ?></td>
                            <td><?= htmlspecialchars($supplier['ContactPerson']) ?></td>
                            <td><?= htmlspecialchars($supplier['ContactPhone']) ?></td>
                            <td><?= htmlspecialchars($supplier['Email']) ?></td>
                            <td><?= htmlspecialchars($supplier['PaymentTerms']) ?></td>
                            <td><?= htmlspecialchars($supplier['Address1']) ?></td>
                            <td><?= htmlspecialchars($supplier['Address2']) ?></td>
                            <td><?= htmlspecialchars($supplier['Remarks']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm me-1" data-supplier-id="<?= htmlspecialchars($supplier['SupplierID']) ?>" data-toggle="modal" data-target="#editSupplierModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="11" class="text-center text-muted">No supplier available. Input new supplier.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="editSupplierModal" tabindex="-1" role="dialog" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg-custom" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editSupplierForm">
                        <input type="hidden" id="editSupplierId" name="supplier_id">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="editCompanyName">Company Name</label>
                                <input type="text" class="form-control" id="editCompanyName" name="company_name" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="editPhone">Phone</label>
                                <input type="text" class="form-control" id="editPhone" name="phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="editContactPerson">Contact Person</label>
                                <input type="text" class="form-control" id="editContactPerson" name="contact_person" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="editContactPhone">Contact Phone</label>
                                <input type="text" class="form-control" id="editContactPhone" name="contact_phone" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="editEmail">Email</label>
                                <input type="email" class="form-control" id="editEmail" name="email" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="editPaymentTerms">Payment Terms</label>
                                <input type="text" class="form-control" id="editPaymentTerms" name="payment_terms" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label for="editAddress1">Address 1</label>
                                <input type="text" class="form-control" id="editAddress1" name="address1" required>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="editAddress2">Address 2</label>
                                <input type="text" class="form-control" id="editAddress2" name="address2">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12 form-group">
                                <label for="editRemarks">Remarks</label>
                                <textarea class="form-control" id="editRemarks" name="remarks"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEditButton">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const menuToggleBtn = document.getElementById('menuToggleBtn');
    const sidebar = document.querySelector('.left-sidebar');

    if (menuToggleBtn && sidebar) {
        menuToggleBtn.addEventListener('click', function(event) {
            sidebar.classList.toggle('active');
            event.stopPropagation();
        });

        document.addEventListener('click', function(event) {
            if (sidebar.classList.contains('active') && 
                !sidebar.contains(event.target) && 
                !menuToggleBtn.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        });

        sidebar.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }

    // Dropdown toggle
    var dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(function(dropdown) {
        var toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', function() {
            dropdown.classList.toggle('active');
        });
    });

    // New Supplier button
    document.getElementById('newProductBtn').addEventListener('click', function() {
        window.location.href = 'NewSupplier.php';
    });

    // Edit Supplier Modal
    $('#editSupplierModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var supplierId = button.data('supplier-id');
        $.get('supplier.php', { supplier_id: supplierId }, function(data) {
            if (data.error) {
                alert(data.error);
                $('#editSupplierModal').modal('hide');
            } else {
                $('#editSupplierId').val(data.SupplierID);
                $('#editCompanyName').val(data.CompanyName);  
                $('#editPhone').val(data.Phone);
                $('#editContactPerson').val(data.ContactPerson);
                $('#editContactPhone').val(data.ContactPhone);
                $('#editEmail').val(data.Email);
                $('#editPaymentTerms').val(data.PaymentTerms);
                $('#editAddress1').val(data.Address1);
                $('#editAddress2').val(data.Address2);
                $('#editRemarks').val(data.Remarks);
            }
        }, 'json');
    });

    document.getElementById('saveEditButton').addEventListener('click', function() {
        var form = document.getElementById('editSupplierForm');
        var data = new FormData(form);
        fetch('supplier.php', {
            method: 'POST',
            body: data
        })
        .then(response => response.text())
        .then(data => {
            if (data === 'success') {
                alert('Supplier updated successfully');
                $('#editSupplierModal').modal('hide');
                updateTable(); // Refresh table after edit
            } else {
                alert('Error updating supplier: ' + data);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating supplier');
        });
    });

    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;

    function updateTable() {
        const searchTerm = searchInput.value.trim();
        const orderBy = document.querySelector('select[name="orderBy"]').value;

        console.log('Updating table with:', { searchTerm, orderBy }); // Debug

        $.ajax({
            url: 'supplier.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy
            },
            success: function(suppliers) {
                console.log('Suppliers received:', suppliers); // Debug log
                const tbody = document.getElementById('supplierTableBody');
                tbody.innerHTML = '';

                if (suppliers.error) {
                    tbody.innerHTML = `<tr><td colspan="11" class="text-center text-danger">${suppliers.error}</td></tr>`;
                    return;
                }

                if (suppliers.length > 0) {
                    suppliers.forEach(supplier => {
                        const row = `
                            <tr>
                                <td>${supplier.SupplierID}</td>
                                <td>${supplier.CompanyName}</td>
                                <td>${supplier.Phone || ''}</td>
                                <td>${supplier.ContactPerson || ''}</td>
                                <td>${supplier.ContactPhone || ''}</td>
                                <td>${supplier.Email || ''}</td>
                                <td>${supplier.PaymentTerms || ''}</td>
                                <td>${supplier.Address1 || ''}</td>
                                <td>${supplier.Address2 || ''}</td>
                                <td>${supplier.Remarks || ''}</td>
                                <td>
                                    <button class="btn btn-warning btn-sm me-1" data-supplier-id="${supplier.SupplierID}" data-toggle="modal" data-target="#editSupplierModal">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted">No supplier available. Input new supplier.</td></tr>';
                }

                // Update the select element to reflect current value
                document.querySelector('select[name="orderBy"]').value = orderBy;
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                document.getElementById('supplierTableBody').innerHTML = '<tr><td colspan="11" class="text-center text-danger">Error loading suppliers</td></tr>';
            }
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300); // 300ms debounce
    });

    // Ensure dropdown triggers table updates
    document.querySelector('select[name="orderBy"]').addEventListener('change', function() {
        console.log('Order By selected:', this.value); // Debug
        updateTable();
    });

    // Initial table load
    updateTable();
});
</script>
</body>
</html>