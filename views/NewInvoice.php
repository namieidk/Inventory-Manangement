<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Supplier Order</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/NewInvoice.css" rel="stylesheet">
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
    <div class="container">
        <h1>New Invoice</h1>
        <form method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Customer Name</label>
                    <input type="text" class="form-control" name="supplier_name" style="width: 400px; height: 40px;">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Invoice Number</label>
                    <input type="text" class="form-control" name="supplier_name" style="width: 400px; height: 40px;">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Order Number</label>
                    <input type="text" class="form-control" name="tin" style="width: 400px; height: 40px;">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Invoice Date</label>
                    <input type="date" class="form-control" name="delivery_date" style="width: 400px; height: 40px;">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Payment Status</label>
                    <input type="text" class="form-control" name="payment_terms" style="width: 400px; height: 40px;">
                </div>
                <div class="col-md-6 mt-3">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-control" name="delivery_date" style="width: 400px; height: 40px;">
                </div>
            </div>
            <h4>Item Table</h4>
            <table class="table item-table">
                <thead>
                    <tr>
                        <th>Order Details</th>
                        <th>Quantity</th>
                        <th>Rate</th>
                        <th>Discount</th>
                        <th>Tax</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Table body will be dynamically added here when an order is placed -->
                </tbody>
            </table>
            <button type="button" class="btn btn-success mt-3">Add Order</button>
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label" style="margin-top: 50px;">Documents</label>
                    <input type="file" class="form-control" style="width: 400px; ">
                </div>
                <div class="col-md-6" style="margin-left: 600px; margin-top: -60px;">
                    <div class="total-section" style="width: 500px; height: 200px;">
                        <p class="m-2" style="margin-top: 10px;"><strong>Sub Total:</strong></p>
                        <p class="m-2" style="margin-top: 10px;"><strong>Discount:</strong></p>
                        <h5 class="m-2" style="margin-top: 10px;"><strong>Total:</strong></h5>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="save" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
    <script>
    document.querySelectorAll('.toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const dropdown = btn.closest('.dropdown');
            dropdown.classList.toggle('active');
        });
    });
    </script>
</body>
</html>
