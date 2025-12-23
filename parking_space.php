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

/* ===== USER INFO ===== */
$name     = $_SESSION["Name"] ?? "Admin";
$initials = strtoupper(substr($name, 0, 2));

// /* ===== QR LIBRARY ===== */
// require_once "lib/phpqrcode/qrlib.php";

// /* ===== GENERATE PARKING SPACES ===== */
// if (isset($_POST["generate"])) {

//     $parkingAreaID = (int)$_POST["parkingAreaID"];

//     $area = $conn->query("
//         SELECT parkingSize 
//         FROM parkingarea 
//         WHERE parkingAreaID = $parkingAreaID
//     ")->fetch_assoc();

//     if ($area) {

//         $size = (int)$area["parkingSize"];

//         $existing = $conn->query("
//             SELECT COUNT(*) AS total 
//             FROM parkingspace 
//             WHERE parkingAreaID = $parkingAreaID
//         ")->fetch_assoc()["total"];

//         if ($existing < $size) {

//             for ($i = $existing + 1; $i <= $size; $i++) {

//                 $stmt = $conn->prepare("
//                     INSERT INTO parkingspace 
//                     (parkingAreaID, number, parkingSpaceStatus)
//                     VALUES (?, ?, 'Available')
//                 ");
//                 $stmt->bind_param("ii", $parkingAreaID, $i);
//                 $stmt->execute();
//                 $spaceID = $stmt->insert_id;
//                 $stmt->close();

//                 $qrData = "http://localhost/fkpark/admin/parking_space_view.php?id=$spaceID";
//                 $qrFile = "qrcodes/space_$spaceID.png";

//                 QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 6);
//             }
//         } else {
//             $error = "Parking spaces already generated for this area.";
//         }
//     }
// }

/* ===== FETCH DATA ===== */
$areas = $conn->query("SELECT parkingAreaID, parkingName FROM parkingarea");

$spaces = $conn->query("
    SELECT ps.*, pa.parkingName 
    FROM parkingspace ps
    JOIN parkingarea pa ON ps.parkingAreaID = pa.parkingAreaID
    ORDER BY pa.parkingName, ps.number
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Parking Space Management</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}
body{background:#fff7ed}

/* ===== NAVBAR ===== */
.navbar{
  background:#ffffff;
  padding:14px 36px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:3px solid #facc15;
}
.nav-left{display:flex;align-items:center;gap:28px}
.logo-box{
  width:58px;height:58px;border-radius:16px;
  border:2px solid #facc15;
  display:flex;align-items:center;justify-content:center
}
.logo-box img{height:38px}
.nav-links{display:flex;gap:14px}
.nav-link{
  text-decoration:none;font-size:15px;color:#374151;
  padding:8px 20px;border-radius:999px
}
.nav-link:hover{background:#fef3c7;color:#92400e}
.nav-link.active{
  background:#facc15;color:#ffffff;font-weight:600
}
.nav-right{display:flex;align-items:center;gap:14px}
.avatar{
  width:42px;height:42px;border-radius:50%;
  border:2px solid #facc15;
  display:flex;align-items:center;justify-content:center;
  background:#fef3c7;color:#92400e;font-weight:700
}
.logout-link{font-size:13px;text-decoration:none;color:#7f1d1d}

/* ===== MAIN ===== */
.main{padding:36px}
h2{color:#92400e;margin-bottom:16px}

select,button{
  padding:8px;border-radius:8px;border:1px solid #e5e7eb
}
button{
  background:#16a34a;color:#fff;border:none;cursor:pointer
}

table{
  width:100%;border-collapse:collapse;background:#fff;margin-top:20px
}
th,td{
  padding:10px;border:1px solid #e5e7eb;text-align:left
}
thead{background:#fef3c7}
img{width:80px}
.error{color:red;margin-bottom:10px}
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
      <a href="parking_area.php" class="nav-link">Parking Areas</a>
      <a href="parking_spaces.php" class="nav-link active">Parking Spaces</a>
    </nav>
  </div>

  <div class="nav-right">
    <div class="avatar"><?= htmlspecialchars($initials) ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<!-- ===== MAIN CONTENT ===== -->
<div class="main">
<h2>Parking Space Management</h2>

<?php if (!empty($error)): ?>
<p class="error"><?= $error ?></p>
<?php endif; ?>

<form method="POST">
  <select name="parkingAreaID" required>
    <option value="">Select Parking Area</option>
    <?php while ($a = $areas->fetch_assoc()): ?>
      <option value="<?= $a["parkingAreaID"] ?>">
        <?= htmlspecialchars($a["parkingName"]) ?>
      </option>
    <?php endwhile; ?>
  </select>
  <button name="generate">Generate Spaces</button>
</form>

<table>
<thead>
<tr>
  <th>Parking Area</th>
  <th>Space Number</th>
  <th>Status</th>
  <th>QR Code</th>
</tr>
</thead>
<tbody>

<?php while ($s = $spaces->fetch_assoc()): ?>
<tr>
  <td><?= htmlspecialchars($s["parkingName"]) ?></td>
  <td><?= $s["number"] ?></td>
  <td><?= $s["parkingSpaceStatus"] ?></td>
  <td>
    <img src="qrcodes/space_<?= $s["parkingSpaceID"] ?>.png">
  </td>
</tr>
<?php endwhile; ?>

</tbody>
</table>
</div>

</body>
</html>
