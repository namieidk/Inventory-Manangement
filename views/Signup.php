<?php
include '../database/database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = $_POST['firstname'] ?? ''; // Changed from 'name' to 'firstname'
    $email = $_POST['email'] ?? '';
    $job_title = $_POST['job_title'] ?? '';
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
            $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $checkStmt->bindParam(':username', $username);
            $checkStmt->execute();
            $usernameExists = $checkStmt->fetchColumn();

            if ($usernameExists > 0) {
                echo "<script>alert('Error: Username already exists. Please choose a different username.');</script>";
            } else {
                // Insert new user with firstname instead of name
                $stmt = $conn->prepare("INSERT INTO users (firstname, email, job_title, username, password) VALUES (:firstname, :email, :job_title, :username, :password)");
                $stmt->bindParam(':firstname', $firstname); // Binding firstname
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':job_title', $job_title);
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
                    <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                    <input type="email" name="email" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Email" required>
                </div>
            </div>

            <div class="mb-3">
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                    <input type="text" name="job_title" class="form-control" style="margin-top: 10px; height: 25px;" placeholder="Job Title" required>
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