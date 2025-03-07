<?php
try {
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "leparisean";

    // Create a PDO connection
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}

?>
