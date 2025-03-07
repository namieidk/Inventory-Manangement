<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Payment.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    
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
        <h1>Payment</h1>
        <div class="d-flex justify-content-between mb-3">
            <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" placeholder="Search...">
            </div>
            <div>
                <button class="btn btn-dark" id="newProductBtn">New <i class="fa fa-plus"></i></button>
                <script>
                    document.getElementById('newProductBtn').addEventListener('click', function() {
                        window.location.href = 'NewCustomer.php';
                    });
                </script>
                <select class="btn btn-outline-secondary">
                    <option>Order By</option>
                    <option>Ascending (A → Z)</option>
                    <option>Descending (Z → A)</option>
                    <option>Low Price (Ascending)</option>
                    <option>High Price (Descending)</option>
                    <option>Newest</option>
                    <option>Oldest</option>
                    <option>Best Seller</option>
                </select>
                <select class="btn btn-outline-secondary">
                    <option>Product Type</option>
                    <option>Product Supplier</option>
                    <option>Below ₱1,000</option>
                    <option>₱1,000 - ₱5,000</option>
                    <option>₱5,000 - ₱10,000</option>
                    <option>Above ₱10,000</option>
                    <option>In-Stock</option>
                    <option>Out of Stock</option>
                </select>
            </div>
        </div>

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Payment ID</th>
                    <th>Payment Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Discount Percentage</th>
                    <th>Created By</th>
                    <th>Created Date</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="7" class="text-center text-muted">No payment available. Input new payment.</td>
                </tr>
            </tbody>
        </table>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var dropdowns = document.querySelectorAll('.dropdown');
        dropdowns.forEach(function(dropdown) {
            var toggleBtn = dropdown.querySelector('.toggle-btn');
            toggleBtn.addEventListener('click', function() {
                dropdown.classList.toggle('active');
            });
        });
    });
</script>
</body>
</html>
