<?php
include '../database/database.php';
include '../database/utils.php';
session_start();

$userId = isset($_SESSION['userId']) ? $_SESSION['userId'] : null;
// Only log if last log was more than X seconds ago
if (!isset($_SESSION['last_UserManagement_log']) || (time() - $_SESSION['last_UserManagement_log']) > 300) { // 300 seconds = 5 minutes
    logAction($conn, $userId, "Accessed User Management Page", "User accessed the User Management page");
    $_SESSION['last_UserManagement_log'] = time();
}
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define $orderBy and $filterBy globally with default empty values
$orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : (isset($_GET['orderBy']) ? $_GET['orderBy'] : '');
$filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : (isset($_GET['filterBy']) ? $_GET['filterBy'] : '');

// Unified fetch function for users
function fetchUsers($conn, $searchTerm = '', $orderBy = '', $filterBy = '') {
    try {
        $sql = "SELECT usersId, firstname, email, role, username, created_at, status FROM users WHERE 1=1";
        $params = [];
        $orderClause = " ORDER BY created_at DESC"; // Default order

        // Search functionality
        if (!empty($searchTerm)) {
            $sql .= " AND (usersId LIKE :search 
                        OR firstname LIKE :search 
                        OR email LIKE :search 
                        OR role LIKE :search 
                        OR username LIKE :search)";
            $params[':search'] = "%$searchTerm%";
        }

        // Filter logic
        switch ($filterBy) {
            case 'job-admin':
                $sql .= " AND role = 'Admin'";
                break;
            case 'job-manager':
                $sql .= " AND role = 'Manager'";
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
            case 'active':
                $sql .= " AND status = 'active'";
                break;
            case 'inactive':
                $sql .= " AND status = 'inactive'";
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
            case 'role-asc':
                $orderClause = " ORDER BY role ASC";
                break;
            case 'role-desc':
                $orderClause = " ORDER BY role DESC";
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
            case 'status-asc':
                $orderClause = " ORDER BY status ASC";
                break;
            case 'status-desc':
                $orderClause = " ORDER BY status DESC";
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
        error_log("Fetch users error: " . $e->getMessage()); // Log to error log
        return ['error' => "Database error: " . $e->getMessage()];
    }
}

// Fetch single user details for edit modal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
    try {
        $stmt = $conn->prepare("SELECT usersId, firstname, email, role, username, status FROM users WHERE usersId = :user_id");
        $stmt->bindParam(':user_id', $_GET['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($user ?: ['error' => 'User not found']);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => "Database error: " . $e->getMessage()]);
        exit;
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $user_id = $_POST['user_id'] ?? '';
    $firstname = $_POST['firstname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $username = $_POST['username'] ?? '';
    $status = $_POST['status'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $sql = "UPDATE users SET firstname = :firstname, email = :email, role = :role, username = :username, status = :status";
        $params = [
            ':user_id' => $user_id,
            ':firstname' => $firstname,
            ':email' => $email,
            ':role' => $role,
            ':username' => $username,
            ':status' => $status
        ];

        if (!empty($password)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = :password";
            $params[':password'] = $password_hash;
        }

        $sql .= " WHERE usersId = :user_id";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => "Database error: " . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX requests for table data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search') {
    $searchTerm = isset($_POST['search']) ? $_POST['search'] : '';
    $orderBy = isset($_POST['orderBy']) ? $_POST['orderBy'] : '';
    $filterBy = isset($_POST['filterBy']) ? $_POST['filterBy'] : '';
    $users = fetchUsers($conn, $searchTerm, $orderBy, $filterBy);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($users, JSON_UNESCAPED_UNICODE);
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
    <title>User Management</title>
    <link href="../statics/bootstrap css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/UserManagement.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
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
    <h1>User Management</h1>
    <div class="d-flex justify-content-between mb-3">
        <div class="search-container">
            <i class="fa fa-search"></i>
            <input type="text" class="form-control" id="searchInput" placeholder="Search by User ID, Name, Email, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
        </div>
        <div>
            <select class="btn btn-outline-secondary" name="orderBy" id="orderBySelect">
                <option value="">Order By</option>
                <option value="firstname-asc" <?php if ($orderBy === 'firstname-asc') echo 'selected'; ?>>First Name (A → Z)</option>
                <option value="firstname-desc" <?php if ($orderBy === 'firstname-desc') echo 'selected'; ?>>First Name (Z → A)</option>
                <option value="email-asc" <?php if ($orderBy === 'email-asc') echo 'selected'; ?>>Email (A → Z)</option>
                <option value="email-desc" <?php if ($orderBy === 'email-desc') echo 'selected'; ?>>Email (Z → A)</option>
                <option value="role-asc" <?php if ($orderBy === 'role-asc') echo 'selected'; ?>>Role (A → Z)</option>
                <option value="role-desc" <?php if ($orderBy === 'role-desc') echo 'selected'; ?>>Role (Z → A)</option>
                <option value="username-asc" <?php if ($orderBy === 'username-asc') echo 'selected'; ?>>Username (A → Z)</option>
                <option value="username-desc" <?php if ($orderBy === 'username-desc') echo 'selected'; ?>>Username (Z → A)</option>
                <option value="status-asc" <?php if ($orderBy === 'status-asc') echo 'selected'; ?>>Status (A → Z)</option>
                <option value="status-desc" <?php if ($orderBy === 'status-desc') echo 'selected'; ?>>Status (Z → A)</option>
                <option value="newest" <?php if ($orderBy === 'newest') echo 'selected'; ?>>Newest</option>
                <option value="oldest" <?php if ($orderBy === 'oldest') echo 'selected'; ?>>Oldest</option>
            </select>
            <select class="btn btn-outline-secondary" name="filterBy" id="filterBySelect">
                <option value="">Filter By</option>
                <option value="job-admin" <?php if ($filterBy === 'job-admin') echo 'selected'; ?>>Role: Admin</option>
                <option value="job-manager" <?php if ($filterBy === 'job-manager') echo 'selected'; ?>>Role: Manager</option>
                <option value="has-email" <?php if ($filterBy === 'has-email') echo 'selected'; ?>>Has Email</option>
                <option value="no-email" <?php if ($filterBy === 'no-email') echo 'selected'; ?>>No Email</option>
                <option value="recently-added" <?php if ($filterBy === 'recently-added') echo 'selected'; ?>>Recently Added (Last 30 Days)</option>
                <option value="older-accounts" <?php if ($filterBy === 'older-accounts') echo 'selected'; ?>>Older Accounts (> 30 Days)</option>
                <option value="active" <?php if ($filterBy === 'active') echo 'selected'; ?>>Active</option>
                <option value="inactive" <?php if ($filterBy === 'inactive') echo 'selected'; ?>>Inactive</option>
            </select>
        </div>
    </div>

    <table class="table table-striped table-hover" id="userTable">
        <thead>
            <tr>
                <th>User ID</th>
                <th>First Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Username</th>
                <th>Created at</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="userTableBody">
            <?php
            if (isset($error)) {
                echo "<tr><td colspan='8' class='text-center text-danger'>" . htmlspecialchars($error) . "</td></tr>";
            } elseif (!empty($users)) {
                foreach ($users as $user) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($user['usersId']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['firstname']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['created_at']) . "</td>";
                    echo "<td>" . htmlspecialchars($user['status']) . "</td>";
                    echo "<td>";
                    echo "<div class='d-flex align-items-center'>";
                    echo "<button class='btn btn-warning btn-sm me-1' data-user-id='" . htmlspecialchars($user['usersId']) . "' data-bs-toggle='modal' data-bs-target='#editUserModal' title='Edit'>";
                    echo "<i class='fas fa-edit'></i>";
                    echo "</button>";
                    echo "</div>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8' class='text-center text-muted'>No users available.</td></tr>";
            }
            ?>
        </tbody>
    </table>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="editUserId" name="user_id">
                        <div class="form-group">
                            <label for="editFirstName">First Name</label>
                            <input type="text" class="form-control" id="editFirstName" name="firstname" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="editRole">Role</label>
                            <input type="text" class="form-control" id="editRole" name="role" required>
                        </div>
                        <div class="form-group">
                            <label for="editUsername">Username</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="editPassword">Password (leave blank to keep unchanged)</label>
                            <input type="password" class="form-control" id="editPassword" name="password" placeholder="Enter new password">
                        </div>
                        <div class="form-group">
                            <label for="editStatus">Status</label>
                            <select class="form-control" id="editStatus" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveEditButton">Save changes</button>
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

    // Search, Order By, and Filter By functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;

    function updateTable() {
        const searchTerm = searchInput.value.trim();
        const orderBy = document.querySelector('select[name="orderBy"]').value;
        const filterBy = document.querySelector('select[name="filterBy"]').value;

        console.log('Updating table with:', { searchTerm, orderBy, filterBy });

        $.ajax({
            url: 'UserManagement.php', // Hardcoded for consistency
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'search',
                search: searchTerm,
                orderBy: orderBy,
                filterBy: filterBy
            },
            success: function(users) {
                console.log('Users received:', users);
                const tbody = document.getElementById('userTableBody');
                tbody.innerHTML = '';

                if (users.error) {
                    tbody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">${users.error}</td></tr>`;
                    return;
                }

                if (users.length > 0) {
                    users.forEach(user => {
                        const row = `
                            <tr>
                                <td>${user.usersId}</td>
                                <td>${user.firstname || ''}</td>
                                <td>${user.email || ''}</td>
                                <td>${user.role || ''}</td>
                                <td>${user.username || ''}</td>
                                <td>${user.created_at || ''}</td>
                                <td>${user.status || ''}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <button class="btn btn-warning btn-sm me-1" data-user-id="${user.usersId}" data-bs-toggle="modal" data-bs-target="#editUserModal" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No users available.</td></tr>';
                }

                // Update select elements to reflect current values
                document.querySelector('select[name="orderBy"]').value = orderBy;
                document.querySelector('select[name="filterBy"]').value = filterBy;

                // Reattach event listeners for buttons
                attachButtonListeners();
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                document.getElementById('userTableBody').innerHTML = '<tr><td colspan="8" class="text-center text-danger">Error loading users: ' + xhr.status + ' - ' + xhr.statusText + '</td></tr>';
            }
        });
    }

    // Debounced search
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(updateTable, 300);
    });

    // Ensure dropdowns trigger table updates
    document.querySelector('select[name="orderBy"]').addEventListener('change', updateTable);
    document.querySelector('select[name="filterBy"]').addEventListener('change', updateTable);

    // Function to attach event listeners to buttons
    function attachButtonListeners() {
        document.querySelectorAll('.btn-warning').forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-user-id');
                console.log('Edit clicked for user ID:', userId);

                // Fetch user details and populate modal
                $.ajax({
                    url: 'UserManagement.php',
                    method: 'GET',
                    dataType: 'json',
                    data: { user_id: userId },
                    success: function(user) {
                        if (user.error) {
                            alert(user.error);
                            $('#editUserModal').modal('hide');
                        } else {
                            $('#editUserId').val(user.usersId);
                            $('#editFirstName').val(user.firstname);
                            $('#editEmail').val(user.email);
                            $('#editRole').val(user.role);
                            $('#editUsername').val(user.username);
                            $('#editStatus').val(user.status);
                            $('#editPassword').val(''); // Clear password field
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX error:', status, error);
                        alert('Error fetching user details');
                        $('#editUserModal').modal('hide');
                    }
                });
            });
        });
    }

    // Save edit button functionality
    document.getElementById('saveEditButton').addEventListener('click', function() {
        const formData = $('#editUserForm').serializeArray();
        const data = { action: 'update' };
        formData.forEach(item => data[item.name] = item.value);

        $.ajax({
            url: 'UserManagement.php',
            method: 'POST',
            dataType: 'json',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert('User updated successfully!');
                    $('#editUserModal').modal('hide');
                    updateTable(); // Refresh the table
                } else {
                    alert(response.error || 'Error updating user');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('Error saving changes');
            }
        });
    });

    // Initial table load and button listener attachment
    updateTable();
    attachButtonListeners();
});
</script>
</body>
</html>