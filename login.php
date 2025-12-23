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

    $name     = trim($_POST["name"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $role     = trim($_POST["role"] ?? "");

    if ($name === "" || $password === "" || $role === "") {
        $error = "Please fill in all fields.";
    } else {

        $roleNorm = strtolower($role);

        $stmt = $conn->prepare("
            SELECT UserID, Name, Password, Role
            FROM login
            WHERE TRIM(Name) = ?
              AND LOWER(REPLACE(TRIM(Role), ' ', '_')) = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $name, $roleNorm);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            // IMPORTANT: do NOT trim hashed password
            $dbPassword  = $row["Password"];
            $sessionRole = strtolower(trim($row["Role"]));
            $sessionRole = str_replace(" ", "_", $sessionRole);

            $ok = false;

            // If password in DB looks like a hash, verify using password_verify
            if (is_string($dbPassword) && strlen($dbPassword) > 0 && $dbPassword[0] === '$') {

                if (password_verify($password, $dbPassword)) {
                    $ok = true;
                }

            } else {
                // Otherwise treat it as plain text (old users)
                if ($password === $dbPassword) {
                    $ok = true;

                    // OPTIONAL: upgrade old plain text to hash automatically
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE login SET Password = ? WHERE UserID = ?");
                    $up->bind_param("ss", $newHash, $row["UserID"]);
                    $up->execute();
                    $up->close();
                }
            }

            if ($ok) {
                $_SESSION["UserID"] = $row["UserID"];
                $_SESSION["Name"]   = $row["Name"];
                $_SESSION["Role"]   = $sessionRole;

                if ($sessionRole === "student") {
                    header("Location: student_board.php");
                } elseif ($sessionRole === "admin") {
                    header("Location: admin_dashboard.php");
                } elseif ($sessionRole === "safety_staff") {
                    header("Location: staff_dashboard.php");
                } else {
                    $error = "Role is not recognized.";
                }
                exit;
            } else {
                $error = "Invalid username, password or role.";
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
    <title>FKpark – Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;}
body{
  min-height:100vh;
  display:flex;
  background: url("pic/parking.jpg") no-repeat center center fixed;
  background-size: cover;
}

        .left,.right{flex:1;display:flex;align-items:center;justify-content:center;padding:40px;}
        .left{border-right:none;}

        .left img{max-width:320px;width:60%;border-radius:24px;background:#fff;padding:24px;box-shadow:0 18px 40px rgba(220,38,38,0.45);}
       .right{background:transparent;}

        .card{max-width:420px;width:100%;background:#fff;border-radius:22px;box-shadow:0 16px 40px rgba(220,38,38,0.3);padding:32px;}
        h1{text-align:center;color:#b91c1c;}
        .subtitle{text-align:center;font-size:13px;color:#6b7280;margin-bottom:20px;}
        .error-box{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;border-radius:10px;padding:10px;margin-bottom:12px;text-align:center;}
        label{font-size:13px;color:#374151;}
        input,select{width:100%;padding:10px;border-radius:10px;border:1px solid #e5e7eb;margin-top:6px;}
        .btn-primary{width:100%;padding:11px;border-radius:999px;border:none;background:linear-gradient(135deg,#dc2626,#f97316);color:#fff;font-weight:600;margin-top:10px;}
    </style>
</head>
<body>

<section class="left">
    <img src="pic/pentakom.png" alt="PETAKOM Logo">
</section>

<section class="right">
    <div class="card">
        <h1>Welcome to FKPark</h1>
        <p class="subtitle">Please login to continue</p>

        <?php if ($error): ?>
            <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label>Username</label>
            <input type="text" name="name" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Select role</label>
            <select name="role" required>
                <option value="" disabled selected>Choose role</option>
                <option value="student">Student</option>
                <option value="admin">Administrator</option>
                <option value="safety_staff">Safety Staff</option>
            </select>

            <button class="btn-primary" type="submit">Login</button>
        </form>
    </div>
</section>

</body>
</html>
