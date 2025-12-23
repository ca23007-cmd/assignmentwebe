<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['Role']) || $_SESSION['Role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $userid   = $_POST['userid'];
    $name     = $_POST['name'];
    $password = $_POST['password'];
    $role     = $_POST['role'];

    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    
    $sql  = "INSERT INTO login (UserID, Name, Password, Role) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $userid, $name, $hashedPassword, $role);

    if ($stmt->execute()) {
        header("Location: admin_user_profile.php?msg=added");
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
