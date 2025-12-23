<?php
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

// Prevent back button
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Only student can access
if (!isset($_SESSION["Role"]) || $_SESSION["Role"] !== "student") {
    header("Location: login.php");
    exit;
}

$name = $_SESSION["Name"] ?? "Student";
$initials = strtoupper(substr($name, 0, 2));

// DB connection
$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/* =========================
   FILTER HANDLING
========================= */
$date = $_GET["date"] ?? "";
$location = $_GET["location"] ?? "";

/* =========================
   SQL QUERY
========================= */
$sql = "
    SELECT 
        ps.parkingSpaceID,
        ps.number,
        ps.parkingSpaceStatus,
        pa.parkingName,
        pa.parkingLocation
    FROM parkingspace ps
    JOIN parkingarea pa 
        ON ps.parkingAreaID = pa.parkingAreaID
    WHERE ps.parkingSpaceStatus = 'Available'
      AND pa.parkingAreaStatus = 'Available'
";

if ($location !== "") {
    $sql .= " AND pa.parkingLocation LIKE ?";
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL Prepare Failed: " . $conn->error);
}

if ($location !== "") {
    $like = "%$location%";
    $stmt->bind_param("s", $like);
}

$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MyPetakom – Parking Booking</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
*{
  margin:0;
  padding:0;
  box-sizing:border-box;
  font-family:system-ui,-apple-system,"Segoe UI",sans-serif;
}

body{
  min-height:100vh;
  display:flex;
  flex-direction:column;
  background: url("pic/red.avif") no-repeat center center fixed;
  background-size: cover;
}

.navbar{
  background:#ffffff;
  padding:12px 32px;
  display:flex;
  justify-content:space-between;
  align-items:center;
  border-bottom:1px solid #fecaca;
  box-shadow:0 4px 14px rgba(248,113,113,0.25);
}

.nav-left{display:flex;align-items:center;gap:24px;}
.logo-box{
  padding:4px 8px;
  border-radius:14px;
  border:1px solid #fecaca;
  background:#ffffff;
}
.logo-box img{height:40px;}

.nav-links{display:flex;gap:12px;}
.nav-link{
  text-decoration:none;
  color:#4b5563;
  padding:7px 16px;
  border-radius:999px;
}
.nav-link:hover{background:#fee2e2;color:#b91c1c;}
.nav-link.active{background:#dc2626;color:#ffffff;}

.nav-right{display:flex;align-items:center;gap:10px;}
.avatar{
  width:38px;height:38px;border-radius:50%;
  border:2px solid #f87171;
  display:flex;align-items:center;justify-content:center;
  background:#fee2e2;color:#b91c1c;font-weight:700;
}
.logout-link{font-size:12px;color:#7f1d1d;text-decoration:none;}

.main{flex:1;padding:26px 40px 40px;}

.page-header h2{
  font-size:22px;
  font-weight:800;
  color:#7f1d1d;
}
.page-header p{
  font-size:13px;
  color:#6b7280;
  margin-bottom:18px;
}

.filter-card{
  background:#ffffff;
  border-radius:18px;
  padding:16px;
  border:1px solid #fecaca;
  box-shadow:0 8px 22px rgba(248,113,113,0.18);
  margin-bottom:20px;
}

.filter-grid{
  display:grid;
  grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
  gap:12px;
}

.label{font-size:12px;font-weight:800;}
.input{
  padding:10px 12px;
  border:1px solid #e5e7eb;
  border-radius:10px;
}
.input:focus{
  outline:none;
  border-color:#f87171;
  box-shadow:0 0 0 3px rgba(248,113,113,0.2);
}

.btn-search{
  background:#dc2626;
  color:#ffffff;
  border:none;
  padding:12px;
  border-radius:14px;
  font-weight:800;
  cursor:pointer;
}

.parking-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(260px,1fr));
  gap:18px;
}

.parking-card{
  background:#ffffff;
  border-radius:18px;
  padding:16px;
  border:1px solid #fecaca;
  box-shadow:0 10px 24px rgba(248,113,113,0.18);
}

.parking-card h3{
  font-size:16px;
  font-weight:800;
  color:#7f1d1d;
  margin-bottom:6px;
}

.parking-info{
  font-size:13px;
  color:#111827;
  margin-bottom:6px;
}

.status{
  font-size:12px;
  font-weight:800;
  color:#166534;
}

.book-btn{
  margin-top:10px;
  display:block;
  text-align:center;
  background:#dc2626;
  color:#fff;
  padding:10px;
  border-radius:12px;
  text-decoration:none;
  font-weight:800;
}
.book-btn:hover{filter:brightness(0.95);}

@media(max-width:960px){
  .main{padding:20px 16px 28px;}
}
</style>
</head>

<body>

<header class="navbar">
  <div class="nav-left">
    <div class="logo-box"><img src="pic/pentakom.png"></div>
    <nav class="nav-links">
      <a href="student_board.php" class="nav-link">Dashboard</a>
      <a href="student_membership.php" class="nav-link">Membership</a>
      <a href="student_parking_booking.php" class="nav-link active">Parking Booking</a>
      <a href="#" class="nav-link">Parking Summon</a>
    </nav>
  </div>
  <div class="nav-right">
    <div class="avatar"><?php echo htmlspecialchars($initials); ?></div>
    <a href="logout.php" class="logout-link">Log out</a>
  </div>
</header>

<main class="main">

  <div class="page-header">
    <h2>Parking Booking</h2>
    <p>View available student parking and make a booking.</p>
  </div>

  <form class="filter-card" method="GET">
    <div class="filter-grid">
      <div>
        <div class="label">Date</div>
        <input type="date" name="date" class="input" value="<?php echo htmlspecialchars($date); ?>">
      </div>

      <div>
        <div class="label">Location</div>
        <input type="text" name="location" class="input"
               placeholder="e.g. Block A"
               value="<?php echo htmlspecialchars($location); ?>">
      </div>

      <div style="align-self:end;">
        <button class="btn-search">SEARCH</button>
      </div>
    </div>
  </form>

  <div class="parking-grid">
    <?php if ($result->num_rows === 0): ?>
      <p style="color:#7f1d1d;font-weight:800;">No available parking spaces.</p>
    <?php endif; ?>

    <?php while ($row = $result->fetch_assoc()): ?>
      <div class="parking-card">
        <h3><?php echo htmlspecialchars($row["parkingName"]); ?></h3>

        <div class="parking-info">
          Location: <?php echo htmlspecialchars($row["parkingLocation"]); ?>
        </div>

        <div class="parking-info">
          Space Number: <?php echo htmlspecialchars($row["number"]); ?>
        </div>

        <div class="status">
          <?php echo htmlspecialchars($row["parkingSpaceStatus"]); ?>
        </div>

        <a class="book-btn"
           href="student_make_booking.php?spaceID=<?php echo (int)$row["parkingSpaceID"]; ?>">
          Book Now
        </a>
      </div>
    <?php endwhile; ?>
  </div>

</main>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
