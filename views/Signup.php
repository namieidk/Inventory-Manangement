<?php
include '../database/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = $_POST['firstname'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate password match
    if ($password !== $confirm_password) {
        echo "<script>alert('Error: Passwords do not match.');</script>";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Check if username already exists
            $checkUsernameStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkUsernameStmt->bindParam(':username', $username);
            $checkUsernameStmt->execute();
            $usernameExists = $checkUsernameStmt->fetchColumn();

            // Check if email already exists (since email is UNIQUE)
            $checkEmailStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $checkEmailStmt->bindParam(':email', $email);
            $checkEmailStmt->execute();
            $emailExists = $checkEmailStmt->fetchColumn();

            if ($usernameExists > 0) {
                echo "<script>alert('Error: Username already exists. Please choose a different username.');</script>";
            } elseif ($emailExists > 0) {
                echo "<script>alert('Error: Email already exists. Please use a different email.');</script>";
            } else {
                // Insert new user with all required fields
                $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, role, username, password) VALUES (:firstname, :lastname, :email, :role, :username, :password)");
                $stmt->bindParam(':firstname', $firstname);
                $stmt->bindParam(':lastname', $lastname);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password_hash);

                $stmt->execute();
                echo "<script>alert('Sign up successful!'); window.location.href = 'Login.php';</script>";
            }
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="../statics/css/bootstrap.min.css" rel="stylesheet">
    <link href="../statics/Signup.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/31e24a5c2a.js" crossorigin="anonymous"></script>
    <style>
        /* Ensure the dropdown matches the input text box size */
        .input-group select.form-control {
            height: 25px; /* Same height as most input boxes */
            margin-top: 10px; /* Same spacing as other fields */
            padding: 0 12px; /* Match padding of text inputs for consistent inner spacing */
            font-size: 16px; /* Default Bootstrap font-size for form-control */
            line-height: 25px; /* Vertically center text */
            width: 150px; /* Ensure it takes full width like text inputs */
            box-sizing: border-box; /* Include padding/border in width/height */
        }
        /* Match the Confirm Password height if needed (optional) */
    
    </style>
</head>
<body>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="eclipse"></div>
    <div class="signup-container">
        <img src="../images/Logo.jpg" alt="Le Parisien" class="logo">
        <h3 class="mb-3" style="margin-top: -10px;">SIGN UP</h3>
        <form method="POST" action="Signup.php">
            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" name="firstname" class="form-control" style="height: 25px;" placeholder="First Name" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" name="lastname" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Last Name" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Email" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user-tag"></i></span>
                    <select name="role" class="form-control" required>
                        <option value="" disabled selected>Select Role</option>
                        <option value="Admin">Admin</option>
                        <option value="Employee">Employee</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                    <input type="text" name="username" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Username" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Password" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="confirm_password" class="form-control" style="margin-top: 10px; height: 30px;" placeholder="Confirm Password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-dark w-100" style="margin-top: 30px; width: 80px; height: 30px; line-height: 10px;">Sign Up</button>
        </form>
        <p class="mt-3">Already have an account? <a href="Login.php">Log in here.</a></p>
    </div>
</body>
</html>