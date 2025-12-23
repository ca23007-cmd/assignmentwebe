<?php
$conn = new mysqli("localhost", "root", "", "fkpark");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}