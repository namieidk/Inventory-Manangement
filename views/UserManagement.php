<?php
include '../database/database.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define $orderBy and $filterBy globally with default empty values
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : (isset($_GET['orderBy']) ? $_GET['orderBy'] : '');
$filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : (isset($_GET['filterBy']) ? $_GET['filterBy'] : '');

// Unified fetch function for users
function fetchUsers($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        $sql = "SELECT usersId, firstname, email, job_title, username, created_at FROM users WHERE 1=1";
        $params = [];
        $orderClause = " ORDER BY created_at DESC"; // Default order

        // Search functionality
        if (!empty($searchTerm)) {
            $sql .= " AND (usersId LIKE :search 
                        OR firstname LIKE :search 
                        OR email LIKE :search 
                        OR job_title LIKE :search 
                        OR username LIKE :search)";
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
            case 'has-email':
                $sql .= " AND email IS NOT NULL AND email != ''";
                break;
            case 'no-email':
                $sql .= " AND (email IS NULL OR email = '')";
                break;
            case 'recently-added':
                $sql .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
            case 'older-accounts':
                $sql .= " AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
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
            case 'username-asc':
                $orderClause = " ORDER BY username ASC";
                break;
            case 'username-desc':
                $orderClause = " ORDER BY username DESC";
                break;
            case 'newest':
                $orderClause = " ORDER BY created_at DESC";
                break;
            case 'oldest':
                $orderClause = " ORDER BY created_at ASC";
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

// Handle AJAX search request
if (isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
    $filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : '';
    $users = fetchUsers($conn, $searchTerm, $orderBy, $filterBy);
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

// Initial page load
$searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
$users = fetchUsers($conn, $searchTerm, $orderBy, $filterBy);
if (isset($users['error'])) {
    $error = $users['error'];
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Dashboard</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/UserManagement.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
                <li><a href="UserManagement.php" style="color: white; text-decoration: none;">User Management</a></li>
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
    <h1>User Management</h1>
    <div class="d-flex justify-content-between mb-3">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by User ID, Name, Email, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div>
            <select class="btn btn-outline-secondary" name="orderBy" id="orderBySelect" onchange="updateTable()">
                <option value="">Order By</option>
                <option value="firstname-asc" <?php if ($orderBy === 'firstname-asc') echo 'selected'; ?>>First Name (A → Z)</option>
                <option value="firstname-desc" <?php if ($orderBy === 'firstname-desc') echo 'selected'; ?>>First Name (Z → A)</option>
                <option value="email-asc" <?php if ($orderBy === 'email-asc') echo 'selected'; ?>>Email (A → Z)</option>
                <option value="email-desc" <?php if ($orderBy === 'email-desc') echo 'selected'; ?>>Email (Z → A)</option>
                <option value="job_title-asc" <?php if ($orderBy === 'job_title-asc') echo 'selected'; ?>>Job Title (A → Z)</option>
                <option value="job_title-desc" <?php if ($orderBy === 'job_title-desc') echo 'selected'; ?>>Job Title (Z → A)</option>
                <option value="username-asc" <?php if ($orderBy === 'username-asc') echo 'selected'; ?>>Username (A → Z)</option>
                <option value="username-desc" <?php if ($orderBy === 'username-desc') echo 'selected'; ?>>Username (Z → A)</option>
                <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
                <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
            </select>
            <select class="btn btn-outline-secondary" name="filterBy" id="filterBySelect" onchange="updateTable()">
                <option value="">Filter By</option>
                <option value="job-admin" <?php if ($filterBy === 'job-admin') echo 'selected'; ?>>Job Title: Admin</option>
                <option value="job-manager" <?php if ($filterBy === 'job-manager') echo 'selected'; ?>>Job Title: Manager</option>
                <option value="has-email" <?php if ($filterBy === 'has-email') echo 'selected'; ?>>Has Email</option>
                <option value="no-email" <?php if ($filterBy === 'no-email') echo 'selected'; ?>>No Email</option>
                <option value="recently-added" <?php if ($filterBy === 'recently-added') echo 'selected'; ?>>Recently Added (Last 30 Days)</option>
                <option value="older-accounts" <?php if ($filterBy === 'older-accounts') echo 'selected'; ?>>Older Accounts (> 30 Days)</option>
            </select>
        </div>
    </div>

    <table class="table table-striped table-hover" id="userTable">
        <thead>
            <tr>
                <th>User ID</th>
                <th>First Name</th>
                <th>Email</th>
                <th>Job Title</th>
                <th>Username</th>
                <th>Created at</th>
            </tr>
        </thead>
        <tbody id="userTableBody">
            <?php
            if (isset($error)) {
                echo "<tr><td colspan='6' class='text-center text-danger'>" . htmlspecialchars($error) . "</td></tr>";
            } elseif (!empty($users)) {
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['usersId']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['firstname']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['job_title']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='6' class='text-center text-muted'>No users available.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dropdown functionality
    const dropdowns = document.querySelectorAll('.dropdown');
    dropdowns.forEach(dropdown => {
        const toggleBtn = dropdown.querySelector('.toggle-btn');
        toggleBtn.addEventListener('click', () => dropdown.classList.toggle('active'));
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
            url: window.location.pathname, // Use current page URL
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy,
                filterBy: filterBy
            },
            success: function(users) {
                console.log('Users received:', users); // Debug log
                const tbody = document.getElementById('userTableBody');
                tbody.innerHTML = '';

                if (users.error) {
                    tbody.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${users.error}</td></tr>`;
                    return;
                }

                if (users.length > 0) {
                    users.forEach(user => {
                        const row = `
                            <tr>
                                <td>${user.usersId}</td>
                                <td>${user.firstname || ''}</td>
                                <td>${user.email || ''}</td>
                                <td>${user.job_title || ''}</td>
                                <td>${user.username || ''}</td>
                                <td>${user.created_at || ''}</td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No users available.</td></tr>';
                }

                // Update the select elements to reflect current values
                document.querySelector('select[name="orderBy"]').value = orderBy;
                document.querySelector('select[name="filterBy"]').value = filterBy;
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                document.getElementById('userTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Error loading users</td></tr>';
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