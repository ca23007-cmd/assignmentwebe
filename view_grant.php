<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "student") {
    header("Location: login.php");
    exit;
}

$name = $_SESSION["Name"] ?? "Student";
$userId = $_SESSION["UserID"] ?? $name;

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$stmt = $conn->prepare("SELECT GrantFile, GrantMime FROM membership WHERE UserID=? LIMIT 1");
$stmt->bind_param("s", $userId);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    http_response_code(404);
    echo "No grant file found.";
    exit;
}

$row = $res->fetch_assoc();
$stmt->close();

if (empty($row["GrantFile"])) {
    http_response_code(404);
    echo "No grant file found.";
    exit;
}

$mime = $row["GrantMime"] ?: "application/octet-stream";
$data = $row["GrantFile"];

header("Content-Type: " . $mime);

$ext = "bin";
if ($mime === "image/jpeg") $ext = "jpg";
if ($mime === "image/png")  $ext = "png";
if ($mime === "application/pdf") $ext = "pdf";

header('Content-Disposition: inline; filename="grant_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $userId) . '.' . $ext . '"');
echo $data;
exit;
