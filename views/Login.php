<?php
include '../database/database.php';
session_start();

$error_message = "";

// Function to log an action into the audit_logs table
function logAction($conn, $username, $action, $description) {
    try {
        $stmt = $conn->prepare("INSERT INTO audit_logs (username, action, description, timestamp) VALUES (:username, :action, :description, NOW())");
        $stmt->execute([
            ':username' => $username,
            ':action' => $action,
            ':description' => $description
        ]);
    } catch (PDOException $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            // Log successful login
            logAction($conn, $username, "Login", "User logged into the system successfully");
            echo "<script>alert('Login successful!'); window.location.href = 'dashboard.php';</script>";
            exit;
        } else {
            // Log failed login attempt
            logAction($conn, $username, "Login Failed", "User failed to log in with incorrect credentials");
            $error_message = "Invalid username or password.";
        }
    } catch (PDOException $e) {
        // Log error if database fails
        logAction($conn, $username, "Login Error", "Database error during login: " . $e->getMessage());
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="../statics/css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Login.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <style>
        .login-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 400px; /* Optional: limits form width */
            margin: 0 auto; /* Centers the container horizontally */
        }
        .input-group {
            margin-top: 10px;
            display: flex;
            align-items: center;
            width: 100%; /* Ensures inputs stretch to container width */
        }
        .input-group-text {
            height: 25px;
            width: 35px; /* Fixed width for consistency */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            margin: 0;
            background-color: #f8f9fa; /* Matches Bootstrap default */
            border: none; /* No border on icon */
        }
        .input-group-text i {
            font-size: 14px;
            line-height: 25px;
        }
        .form-control {
            height: 25px;
            padding: 0 10px;
            line-height: 25px;
            margin: 0;
            border: 1px solid #ced4da;
            width: 80%; /* Ensures input fills remaining space */
            border-radius: 4px; /* Small curved borders on all sides */
        }
        form {
            width: 50%; /* Fixed from '50' */
        }
    </style>
</head>
<body>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="login-container">
        <img src="../images/Logo.jpg" alt="Le Parisien" class="logo">
        <h3 class="mb-3" style="margin-top: -10px;">USER LOGIN</h3>
        <?php if (!empty($error_message)) echo "<div class='alert alert-danger'>$error_message</div>"; ?>
        <form method="POST" action="login.php">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user-tag"></i></span>
                    <input type="text" name="role" class="form-control" placeholder="Role" required>
                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100" style="width: 80px; height: 20px; margin-top: 20px">Login</button>
        </form>
        
        <div class="separator"><span style="font-size: 20px;">or</span></div>
        
        <button class="btn btn-light w-100 d-flex align-items-center justify-content-center">
            <img src="../images/google-icon.svg" alt="Google" class="me-2" style="width: 20px; height: 20px;">
            <span>Continue with Email</span>
        </button>
        <p class="mt-3">Don’t have an account? <a href="Signup.php">Sign up here.</a></p>
    </div>
</body>
</html>