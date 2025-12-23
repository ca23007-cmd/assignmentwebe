<?php
session_start();
error_reporting(E_ALL);
ini_set("display_errors", 1);

/* ===== ADMIN AUTH ===== */
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "admin") {
    header("Location: login.php");
    exit;
}

/* ===== DB CONNECTION ===== */
$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name     = $_SESSION["Name"] ?? "Admin";
$initials = strtoupper(substr($name, 0, 2));

/* ===== DELETE PARKING AREA ===== */
if (isset($_GET["delete"])) {
    $deleteID = (int)$_GET["delete"];

    $stmt = $conn->prepare("DELETE FROM parkingarea WHERE parkingAreaID = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $deleteID);

    if (!$stmt->execute()) {
        die("Delete failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: parking_area.php");
    exit;
}

/* ===== LOAD DATA FOR EDIT ===== */
$editData = null;
if (isset($_GET["edit"])) {
    $editID = (int)$_GET["edit"];

    $stmt = $conn->prepare("SELECT * FROM parkingarea WHERE parkingAreaID = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("i", $editID);
    $stmt->execute();
    $editData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$editData) {
        header("Location: parking_area.php");
        exit;
    }
}

/* ===== UPDATE PARKING AREA ===== */
if (isset($_POST["update"])) {
    $parkingAreaID = (int)($_POST["parkingAreaID"] ?? 0);
    $nameP         = trim($_POST["parkingName"] ?? "");
    $location      = trim($_POST["parkingLocation"] ?? "");
    $size          = (int)($_POST["parkingSize"] ?? 0);
    $status        = trim($_POST["parkingAreaStatus"] ?? "");
    $description   = trim($_POST["Description"] ?? "");
    $start         = !empty($_POST["closed_start_date"]) ? $_POST["closed_start_date"] : NULL;
    $end           = !empty($_POST["closed_end_date"]) ? $_POST["closed_end_date"] : NULL;

    $stmt = $conn->prepare("
        UPDATE parkingarea SET
            parkingName = ?,
            parkingLocation = ?,
            parkingSize = ?,
            parkingAreaStatus = ?,
            description = ?,
            closed_start_date = ?,
            closed_end_date = ?
        WHERE parkingAreaID = ?
    ");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssissssi",
        $nameP,
        $location,
        $size,
        $status,
        $description,
        $start,
        $end,
        $parkingAreaID
    );

    if (!$stmt->execute()) {
        die("Update failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: parking_area.php");
    exit;
}

/* ===== ADD PARKING AREA ===== */
if (isset($_POST["add"])) {

    $parkingAreaID = (int)($_POST["parkingAreaID"] ?? 0);
    $userID        = $_SESSION["UserID"] ?? "";
    $nameP         = trim($_POST["parkingName"] ?? "");
    $location      = trim($_POST["parkingLocation"] ?? "");
    $size          = (int)($_POST["parkingSize"] ?? 0);
    $status        = trim($_POST["parkingAreaStatus"] ?? "");
    $description   = trim($_POST["Description"] ?? "");
    $start         = !empty($_POST["closed_start_date"]) ? $_POST["closed_start_date"] : NULL;
    $end           = !empty($_POST["closed_end_date"]) ? $_POST["closed_end_date"] : NULL;

    $stmt = $conn->prepare("
        INSERT INTO parkingarea
        (parkingAreaID, userID, parkingName, parkingLocation, parkingSize, parkingAreaStatus, description, closed_start_date, closed_end_date)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "issssssss",
        $parkingAreaID,
        $userID,
        $nameP,
        $location,
        $size,
        $status,
        $description,
        $start,
        $end
    );

    if (!$stmt->execute()) {
        die("Insert failed: " . $stmt->error);
    }
    $stmt->close();

    header("Location: parking_area.php");
    exit;
}

/* ===== FETCH PARKING AREAS ===== */
$result = $conn->query("SELECT * FROM parkingarea ORDER BY parkingAreaID DESC");
if (!$result) {
    die("Query failed: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parking Area Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}
body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background: url("pic/yellow.avif") no-repeat center center fixed;
  background-size: cover;
}

/* ===== NAVBAR (MATCHED) ===== */
.navbar{
  background:#ffffff;
  padding:14px 36px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:3px solid #facc15;
}

.nav-left{
  display:flex;
  align-items:center;
  gap:28px;
}

.logo-box{
  width:58px;
  height:58px;
  border-radius:16px;
  border:2px solid #facc15;
  display:flex;
  align-items:center;
  justify-content:center;
}

.logo-box img{
  height:38px;
}

.nav-links{
  display:flex;
  gap:14px;
}

.nav-link{
  text-decoration:none;
  font-size:15px;
  color:#374151;
  padding:8px 20px;
  border-radius:999px;
}

.nav-link:hover{
  background:#fef3c7;
  color:#92400e;
}

.nav-link.active{
  background:#facc15;
  color:#ffffff;
  font-weight:600;
}

.nav-right{
  display:flex;
  align-items:center;
  gap:14px;
}

.avatar{
  width:42px;
  height:42px;
  border-radius:50%;
  border:2px solid #facc15;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#fef3c7;
  color:#92400e;
  font-weight:700;
}

.logout-link{
  font-size:13px;
  text-decoration:none;
  color:#7f1d1d;
}

/* ===== MAIN ===== */
.main{
  padding:36px;
}

h2{
  color:#92400e;
  margin-bottom:16px;
}

form input, form select{
  padding:8px;
  margin-right:6px;
  border-radius:8px;
  border:1px solid #e5e7eb;
}

.btn-add{
  padding:8px 16px;
  background:#16a34a;
  color:#fff;
  border:none;
  border-radius:8px;
  cursor:pointer;
}

/* cancel button added */
.btn-cancel{
  display:inline-block;
  padding:8px 16px;
  background:#6b7280;
  color:#fff;
  border-radius:8px;
  text-decoration:none;
  margin-left:10px;
}

table{
  width:100%;
  border-collapse:separate;
  border-spacing:0;
  background:#ffffff;
  margin-top:20px;
  border-radius:12px;
  overflow:hidden;
  box-shadow:0 10px 20px rgba(0,0,0,0.08);
}

thead{
  background:#facc15;
}

thead th{
  color:#92400e;
  font-weight:600;
  text-transform:uppercase;
  font-size:13px;
}

th,td{
  padding:12px 14px;
  border-bottom:1px solid #e5e7eb;
  text-align:left;
}

tbody tr:hover{
  background:#fff7ed;
}

tbody tr:last-child td{
  border-bottom:none;
}

.btn-del{
  background:#dc2626;
  color:#fff;
  padding:6px 12px;
  border-radius:6px;
  text-decoration:none;
  margin-left:6px;
}

.btn-edit{
  background:#2563eb;
  color:#fff;
  padding:6px 12px;
  border-radius:6px;
  text-decoration:none;
}
</style>
</head>

<body>

<!-- ===== NAVBAR ===== -->
<header class="navbar">
  <div class="nav-left">
    <div class="logo-box">
      <img src="pic/pentakom.png" alt="Logo">
    </div>

    <nav class="nav-links">
      <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
      <a href="admin_user_profile.php" class="nav-link">User Profile Management</a>
      <a href="parking_area.php" class="nav-link active">Parking Areas</a>
      <a href="parking_space.php" class="nav-link">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
    <a href="admin_profile.php" class="avatar-link" title="Admin Profile">
      <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    </a>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<!-- ===== MAIN CONTENT ===== -->
<div class="main">
  <h2>Parking Area Management</h2>

  <form method="POST">
    <input type="number" name="parkingAreaID" placeholder="Parking Area ID"
           value="<?= htmlspecialchars($editData["parkingAreaID"] ?? "") ?>"
           <?= $editData ? "readonly" : "required" ?>>

    <input type="text" name="parkingName" placeholder="Parking Area Name" required
           value="<?= htmlspecialchars($editData["parkingName"] ?? "") ?>">

    <input type="text" name="parkingLocation" placeholder="Location" required
           value="<?= htmlspecialchars($editData["parkingLocation"] ?? "") ?>">

    <input type="number" name="parkingSize" placeholder="Parking Box" required
           value="<?= htmlspecialchars($editData["parkingSize"] ?? "") ?>">

    <select name="parkingAreaStatus" required>
      <option value="Open"   <?= (($editData["parkingAreaStatus"] ?? "") === "Open") ? "selected" : "" ?>>Open</option>
      <option value="Closed" <?= (($editData["parkingAreaStatus"] ?? "") === "Closed") ? "selected" : "" ?>>Closed</option>
    </select>

    <input type="text" name="Description" placeholder="Description" required
           value="<?= htmlspecialchars($editData["description"] ?? "") ?>"><br><br>

    <label>Closed Start</label>
    <input type="datetime-local" name="closed_start_date"
           value="<?= !empty($editData["closed_start_date"]) ? htmlspecialchars($editData["closed_start_date"]) : "" ?>">

    <label>Closed End</label>
    <input type="datetime-local" name="closed_end_date"
           value="<?= !empty($editData["closed_end_date"]) ? htmlspecialchars($editData["closed_end_date"]) : "" ?>">

    <?php if ($editData): ?>
      <button class="btn-add" name="update">Update Parking Area</button>
      <a href="parking_area.php" class="btn-cancel">Cancel</a>
    <?php else: ?>
      <button class="btn-add" name="add">Add Parking Area</button>
    <?php endif; ?>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th><th>Name</th><th>Location</th><th>Parking Box</th>
        <th>Status</th><th>Description</th><th>Start</th><th>End</th><th>Action</th>
      </tr>
    </thead>
    <tbody>

    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= (int)$row["parkingAreaID"] ?></td>
        <td><?= htmlspecialchars($row["parkingName"]) ?></td>
        <td><?= htmlspecialchars($row["parkingLocation"]) ?></td>
        <td><?= (int)$row["parkingSize"] ?></td>
        <td><?= htmlspecialchars($row["parkingAreaStatus"]) ?></td>
        <td><?= htmlspecialchars($row["description"]) ?></td>
        <td><?= !empty($row["closed_start_date"]) ? htmlspecialchars($row["closed_start_date"]) : "-" ?></td>
        <td><?= !empty($row["closed_end_date"]) ? htmlspecialchars($row["closed_end_date"]) : "-" ?></td>
        <td>
          <a class="btn-edit" href="?edit=<?= (int)$row["parkingAreaID"] ?>">Edit</a>
          <a class="btn-del"
             href="?delete=<?= (int)$row["parkingAreaID"] ?>"
             onclick="return confirm('Delete this parking area?')">
             Delete
          </a>
        </td>
      </tr>
    <?php endwhile; ?>

    </tbody>
  </table>
</div>

</body>
</html>
