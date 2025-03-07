<?php
include '../database/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define $orderBy and $filterBy globally with default empty values
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : (isset($_GET['orderBy']) ? $_GET['orderBy'] : '');
$filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : (isset($_GET['filterBy']) ? $_GET['filterBy'] : '');

// Only process POST requests for form submission (saving new employee)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firstname'])) {
    header('Content-Type: application/json'); // Set JSON header for AJAX response
    
    try {
        $stmt = $conn->prepare("INSERT INTO employees (firstname, lastname, email, job_title, phone, birth_date) 
                              VALUES (:firstname, :lastname, :email, :job_title, :phone, :birth_date)");

        // Bind parameters
        $stmt->bindParam(':firstname', $_POST['firstname']);
        $stmt->bindParam(':lastname', $_POST['lastname']);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':job_title', $_POST['job_title']);
        $stmt->bindParam(':phone', $_POST['phone']);
        $stmt->bindParam(':birth_date', $_POST['birth_date']);

        $stmt->execute();

        echo json_encode(['success' => true]);
        exit;
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Unified fetch function for employees
function fetchEmployees($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        $sql = "SELECT * FROM employees WHERE 1=1";
        $params = [];
        $orderClause = " ORDER BY employee_id DESC"; // Default order

        // Search functionality
        if (!empty($searchTerm)) {
            $sql .= " AND (employee_id LIKE :search 
                        OR firstname LIKE :search 
                        OR lastname LIKE :search 
                        OR email LIKE :search 
                        OR job_title LIKE :search 
                        OR phone LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Filter logic
        switch ($filterBy) {
            case 'job-admin':
                $sql .= " AND job_title = 'Admin'";
                break;
            case 'job-manager':
                $sql .= " AND job_title = 'Manager'";
                break;
            case 'age-18-30':
                $sql .= " AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 18 AND 30";
                break;
            case 'age-31-50':
                $sql .= " AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 31 AND 50";
                break;
            case 'age-above-50':
                $sql .= " AND TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) > 50";
                break;
            case 'has-email':
                $sql .= " AND email IS NOT NULL AND email != ''";
                break;
            case 'no-email':
                $sql .= " AND (email IS NULL OR email = '')";
                break;
            default:
                break;
        }

        // Order logic
        switch ($orderBy) {
            case 'firstname-asc':
                $orderClause = " ORDER BY firstname ASC";
                break;
            case 'firstname-desc':
                $orderClause = " ORDER BY firstname DESC";
                break;
            case 'lastname-asc':
                $orderClause = " ORDER BY lastname ASC";
                break;
            case 'lastname-desc':
                $orderClause = " ORDER BY lastname DESC";
                break;
            case 'email-asc':
                $orderClause = " ORDER BY email ASC";
                break;
            case 'email-desc':
                $orderClause = " ORDER BY email DESC";
                break;
            case 'job_title-asc':
                $orderClause = " ORDER BY job_title ASC";
                break;
            case 'job_title-desc':
                $orderClause = " ORDER BY job_title DESC";
                break;
            case 'phone-asc':
                $orderClause = " ORDER BY phone ASC";
                break;
            case 'phone-desc':
                $orderClause = " ORDER BY phone DESC";
                break;
            case 'birth_date-asc':
                $orderClause = " ORDER BY birth_date ASC";
                break;
            case 'birth_date-desc':
                $orderClause = " ORDER BY birth_date DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY employee_id DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY employee_id ASC";
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
    } catch(PDOException $e) {
        return ['error' => "Database error: " . $e->getMessage()];
    }
}

// Handle AJAX search request
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
    $filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : '';
    $employees = fetchEmployees($conn, $searchTerm, $orderBy, $filterBy);
    header('Content-Type: application/json');
    echo json_encode($employees);
    exit;
}

// Initial page load
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$employees = fetchEmployees($conn, $searchTerm, $orderBy, $filterBy);
if (isset($employees['error'])) {
    $error = $employees['error'];
    $employees = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Employees.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Add minimal styling to ensure select boxes render properly */
        select.btn.btn-outline-secondary {
            appearance: auto;
            padding: 5px;
            min-width: 150px;
            margin-left: 10px;
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
    <h1>Employees</h1>
    <div class="d-flex justify-content-between mb-3">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by Employee ID, Name, Email, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div>
            <button class="btn btn-dark" id="newProductBtn" data-bs-toggle="modal" data-bs-target="#employeeModal">New <i class="fa fa-plus"></i></button>
            <select class="btn btn-outline-secondary" name="orderBy" id="orderBySelect" onchange="updateTable()">
                <option value="">Order By</option>
                <option value="firstname-asc" <?php if ($orderBy === 'firstname-asc') echo 'selected'; ?>>First Name (A → Z)</option>
                <option value="firstname-desc" <?php if ($orderBy === 'firstname-desc') echo 'selected'; ?>>First Name (Z → A)</option>
                <option value="lastname-asc" <?php if ($orderBy === 'lastname-asc') echo 'selected'; ?>>Last Name (A → Z)</option>
                <option value="lastname-desc" <?php if ($orderBy === 'lastname-desc') echo 'selected'; ?>>Last Name (Z → A)</option>
                <option value="email-asc" <?php if ($orderBy === 'email-asc') echo 'selected'; ?>>Email (A → Z)</option>
                <option value="email-desc" <?php if ($orderBy === 'email-desc') echo 'selected'; ?>>Email (Z → A)</option>
                <option value="job_title-asc" <?php if ($orderBy === 'job_title-asc') echo 'selected'; ?>>Job Title (A → Z)</option>
                <option value="job_title-desc" <?php if ($orderBy === 'job_title-desc') echo 'selected'; ?>>Job Title (Z → A)</option>
                <option value="phone-asc" <?php if ($orderBy === 'phone-asc') echo 'selected'; ?>>Phone (A → Z)</option>
                <option value="phone-desc" <?php if ($orderBy === 'phone-desc') echo 'selected'; ?>>Phone (Z → A)</option>
                <option value="birth_date-asc" <?php if ($orderBy === 'birth_date-asc') echo 'selected'; ?>>Birth Date (Oldest First)</option>
                <option value="birth_date-desc" <?php if ($orderBy === 'birth_date-desc') echo 'selected'; ?>>Birth Date (Youngest First)</option>
                <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
                <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
            </select>
            <select class="btn btn-outline-secondary" name="filterBy" id="filterBySelect" onchange="updateTable()">
                <option value="">Filter By</option>
                <option value="job-admin" <?php if ($filterBy === 'job-admin') echo 'selected'; ?>>Job Title: Admin</option>
                <option value="job-manager" <?php if ($filterBy === 'job-manager') echo 'selected'; ?>>Job Title: Manager</option>
                <option value="age-18-30" <?php if ($filterBy === 'age-18-30') echo 'selected'; ?>>Age: 18-30</option>
                <option value="age-31-50" <?php if ($filterBy === 'age-31-50') echo 'selected'; ?>>Age: 31-50</option>
                <option value="age-above-50" <?php if ($filterBy === 'age-above-50') echo 'selected'; ?>>Age: Above 50</option>
                <option value="has-email" <?php if ($filterBy === 'has-email') echo 'selected'; ?>>Has Email</option>
                <option value="no-email" <?php if ($filterBy === 'no-email') echo 'selected'; ?>>No Email</option>
            </select>
        </div>
    </div>

    <table class="table table-striped table-hover" id="employeeTable">
        <thead>
            <tr>
                <th>Employee ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Job Title</th>
                <th>Phone</th>
                <th>Birth Date</th>
            </tr>
        </thead>
        <tbody id="employeeTableBody">
            <?php if (isset($error)): ?>
                <tr>
                    <td colspan="7" class="text-center text-danger"><?php echo htmlspecialchars($error); ?></td>
                </tr>
            <?php elseif (empty($employees)): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No Employees available. Input new employee.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                        <td><?php echo htmlspecialchars($employee['firstname']); ?></td>
                        <td><?php echo htmlspecialchars($employee['lastname']); ?></td>
                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                        <td><?php echo htmlspecialchars($employee['job_title']); ?></td>
                        <td><?php echo htmlspecialchars($employee['phone']); ?></td>
                        <td><?php echo htmlspecialchars($employee['birth_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="modal fade" id="employeeModal" tabindex="-1" aria-labelledby="employeeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="employeeModalLabel">New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="newEmployeeForm">
                        <div class="row mb-3">
                            <div class="col">
                                <label for="firstName">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="firstname" required>
                            </div>
                            <div class="col">
                                <label for="lastName">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="lastname" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="jobTitle">Job Title</label>
                            <input type="text" class="form-control" id="jobTitle" name="job_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="mb-3">
                            <label for="birthDate">Birth Date</label>
                            <input type="date" class="form-control" id="birthDate" name="birth_date" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEmployeeBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', () => dropdown.classList.toggle('active'));
    });

    // Save employee functionality
    document.getElementById('saveEmployeeBtn').addEventListener('click', function() {
        const form = document.getElementById('newEmployeeForm');
        
        if (form.checkValidity()) {
            const formData = new FormData(form);
            
            fetch('Employees.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Employee saved successfully!');
                    bootstrap.Modal.getInstance(document.getElementById('employeeModal')).hide();
                    updateTable(); // Refresh table instead of reloading page
                } else {
                    alert('Error saving employee: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the employee');
            });
        } else {
            form.reportValidity();
        }
    });

    // Search, Order By, and Filter By functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;

    function updateTable() {
        const searchTerm = searchInput.value.trim();
        const orderBy = document.querySelector('select[name="orderBy"]').value;
        const filterBy = document.querySelector('select[name="filterBy"]').value;

        console.log('Updating table with:', { searchTerm, orderBy, filterBy }); // Debug

        $.ajax({
            url: 'Employees.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy,
                filterBy: filterBy
            },
            success: function(employees) {
                console.log('Employees received:', employees); // Debug log
                const tbody = document.getElementById('employeeTableBody');
                tbody.innerHTML = '';

                if (employees.error) {
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">${employees.error}</td></tr>`;
                    return;
                }

                if (employees.length > 0) {
                    employees.forEach(employee => {
                        const row = `
                            <tr>
                                <td>${employee.employee_id}</td>
                                <td>${employee.firstname || ''}</td>
                                <td>${employee.lastname || ''}</td>
                                <td>${employee.email || ''}</td>
                                <td>${employee.job_title || ''}</td>
                                <td>${employee.phone || ''}</td>
                                <td>${employee.birth_date || ''}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No Employees available. Input new employee.</td></tr>';
                }

                // Update the select elements to reflect current values
                document.querySelector('select[name="orderBy"]').value = orderBy;
                document.querySelector('select[name="filterBy"]').value = filterBy;
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                document.getElementById('employeeTableBody').innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading employees</td></tr>';
            }
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300); // 300ms debounce
    });

    // Ensure dropdowns trigger table updates
    document.querySelector('select[name="orderBy"]').addEventListener('change', function() {
        console.log('Order By selected:', this.value); // Debug
        updateTable();
    });

    document.querySelector('select[name="filterBy"]').addEventListener('change', function() {
        console.log('Filter By selected:', this.value); // Debug
        updateTable();
    });

    // Initial table load
    updateTable();
});
</script>
</body>
</html>