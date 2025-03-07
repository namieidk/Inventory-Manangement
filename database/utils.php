<?php
// utils.php

function logAction($conn, $userId, $action, $description) {
    try {
        $stmt = $conn->prepare("CALL LogAction(?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            $description
        ]);
        error_log("Successfully logged action: $action - $description");
    } catch (PDOException $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}
?>