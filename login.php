<?php
ob_start();
session_start();

error_reporting(E_ALL);
ini_set("display_errors", 1);

$conn = new mysqli("localhost", "root", "", "fkpark");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name     = trim($_POST["name"]     ?? "");
    $password = trim($_POST["password"] ?? "");
    // normalise role to lowercase without extra spaces
    $role     = strtolower(trim($_POST["role"] ?? ""));

    if ($name === "" || $password === "" || $role === "") {
        $error = "Please fill in all fields.";
    } else {
        // compare role in lowercase so it’s not sensitive to case in DB
        $stmt = $conn->prepare(
            "SELECT UserID, Name, Password, Role
             FROM login
             WHERE Name = ? AND Password = ? AND LOWER(Role) = ?"
        );
        $stmt->bind_param("sss", $name, $password, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $_SESSION["UserID"] = $row["UserID"];
            $_SESSION["Name"]   = $row["Name"];
            $_SESSION["Role"]   = strtolower(trim($row["Role"])); // normalised session role

            if ($_SESSION["Role"] === "student") {
                header("Location: student_board.php");
                exit;
            } elseif ($_SESSION["Role"] === "admin") {
                header("Location: admin_dashboard.php");
                exit;
            } elseif ($_SESSION["Role"] === "safety staff") {
                header("Location: staff_dashboard.php");
                exit;
            } else {
                $error = "Role is not recognized.";
            }
        } else {
            $error = "Invalid username, password or role.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MyPetakom – Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    *{
      margin:0;
      padding:0;
      box-sizing:border-box;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
    }
    body{
      min-height:100vh;
      display:flex;
      background:linear-gradient(135deg,#ffe2e2,#ffcaca,#ffdede);
      color:#222;
    }
    .left,
    .right{
      flex:1;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:40px;
    }
    .left{
      border-right:1px solid rgba(255,255,255,0.5);
    }
    .left img{
      max-width:320px;
      width:60%;
      border-radius:24px;
      background:#fff;
      padding:24px;
      box-shadow:0 18px 40px rgba(220,38,38,0.45);
    }
    .right{
      background:#fff7f7;
    }
    .card{
      width:100%;
      max-width:420px;
      background:#ffffff;
      border-radius:22px;
      box-shadow:0 16px 40px rgba(220,38,38,0.3);
      padding:32px 32px 26px;
    }
    .card h1{
      font-size:24px;
      margin-bottom:6px;
      text-align:center;
      color:#b91c1c;
    }
    .card p.subtitle{
      font-size:13px;
      text-align:center;
      margin-bottom:22px;
      color:#6b7280;
    }
    .error-box{
      background:#fee2e2;
      border:1px solid #fecaca;
      color:#b91c1c;
      border-radius:10px;
      padding:10px 12px;
      font-size:13px;
      margin-bottom:14px;
      text-align:center;
    }
    .form-group{
      margin-bottom:16px;
    }
    label{
      display:block;
      font-size:13px;
      margin-bottom:6px;
      color:#374151;
    }
    input[type="text"],
    input[type="password"],
    select{
      width:100%;
      padding:10px 12px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      font-size:14px;
      outline:none;
      background:#f9fafb;
      transition:border 0.15s, box-shadow 0.15s, background 0.15s;
    }
    input:focus,
    select:focus{
      border-color:#f97316;
      box-shadow:0 0 0 2px rgba(248,113,113,0.3);
      background:#ffffff;
    }
    .password-wrapper{
      position:relative;
    }
    .password-toggle{
      position:absolute;
      right:10px;
      top:50%;
      transform:translateY(-50%);
      border:none;
      background:transparent;
      cursor:pointer;
      font-size:12px;
      color:#b91c1c;
    }
    .btn-primary{
      width:100%;
      padding:11px;
      border-radius:999px;
      border:none;
      background:linear-gradient(135deg,#dc2626,#f97316);
      color:#fff;
      font-size:15px;
      font-weight:600;
      cursor:pointer;
      margin-top:4px;
      margin-bottom:8px;
      transition:transform 0.12s, box-shadow 0.12s, filter 0.12s;
      box-shadow:0 10px 24px rgba(220,38,38,0.5);
    }
    .btn-primary:hover{
      filter:brightness(1.05);
      transform:translateY(-1px);
      box-shadow:0 14px 30px rgba(220,38,38,0.55);
    }
    .btn-primary:active{
      transform:translateY(0);
      box-shadow:0 6px 16px rgba(220,38,38,0.4);
    }
    .signup{
      text-align:center;
      font-size:13px;
      margin-top:8px;
      color:#6b7280;
    }
    .signup a{
      color:#b91c1c;
      font-weight:600;
      text-decoration:none;
    }
    .signup a:hover{
      text-decoration:underline;
    }
    @media(max-width:768px){
      body{
        flex-direction:column;
      }
      .left{
        display:none;
      }
      .right{
        padding:24px;
      }
      .card{
        box-shadow:0 8px 22px rgba(220,38,38,0.3);
      }
    }
  </style>
</head>
<body>

<section class="left">
  <img src="pic/petakom.jpeg" alt="PETAKOM logo">
</section>

<section class="right">
  <div class="card">
    <h1>Welcome to MyPetakom</h1>
    <p class="subtitle">Please login to continue</p>

    <?php if ($error !== ""): ?>
      <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
      <div class="form-group">
        <label for="name">Username</label>
        <input type="text" id="name" name="name" required>
      </div>

      <div class="form-group">
        <label for="password">Password</label>
        <div class="password-wrapper">
          <input type="password" id="password" name="password" required>
          <button type="button" class="password-toggle" onclick="togglePassword()">Show</button>
        </div>
      </div>

      <div class="form-group">
        <label for="role">Select role</label>
        <select id="role" name="role" required>
          <option value="" disabled selected>Choose your role</option>
          <option value="student">Student</option>
          <option value="admin">Administrator</option>
          <option value="safety staff">Safety Staff</option>
        </select>
      </div>

      <button type="submit" class="btn-primary">Login</button>

      <div class="signup">
        Do not have an account yet?
        <a href="#">Contact administrator</a>
      </div>
    </form>
  </div>
</section>

<script>
function togglePassword(){
  const input = document.getElementById("password");
  const btn = document.querySelector(".password-toggle");
  if (input.type === "password") {
    input.type = "text";
    btn.textContent = "Hide";
  } else {
    input.type = "password";
    btn.textContent = "Show";
  }
}
</script>

</body>
</html>
