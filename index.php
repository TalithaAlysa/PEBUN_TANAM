<?php
session_start();
include "koneksi.php";

$error = "";

if (isset($_POST['login'])) {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = md5($_POST['password']);

    $query = mysqli_query($koneksi, "SELECT * FROM users
        WHERE username='$username'
        AND password='$password'
        AND status='aktif'");

    $data = mysqli_fetch_assoc($query);

    if ($data) {
        $_SESSION['id_user'] = $data['id_user'];
        $_SESSION['nama'] = $data['nama'];
        $_SESSION['level'] = $data['level'];

        // Catat aktivitas login
        $id_user = (int) $data['id_user'];
        $aktivitas = "Login ke sistem";

        mysqli_query($koneksi, "INSERT INTO log_aktivitas
            (id_user, aktivitas, waktu)
            VALUES ($id_user, '$aktivitas', NOW())");

        // Arahkan sesuai level pengguna
        if ($data['level'] == 'admin') {
            header("Location: admin/dashboard.php");
        } elseif ($data['level'] == 'petugas') {
            header("Location: petugas/dashboard.php");
        } else {
            header("Location: peminjam/dashboard.php");
        }
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - KEBUN PINJAM</title>

    <!-- Bootstrap offline -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">

    <style>
        body {
            background: #E8EDE6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .login {
            background: white;
            width: 100%;
            max-width: 400px;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .judul {
            color: #557355;
            font-weight: bold;
            text-align: center;
            margin-bottom: 8px;
        }

        .deskripsi {
            text-align: center;
            color: #777;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #444;
        }

        .form-control {
            padding: 10px 12px;
            border-radius: 7px;
        }

        .form-control:focus {
            border-color: #6B8E6B;
            box-shadow: 0 0 0 0.2rem rgba(107, 142, 107, 0.2);
        }

        .btn-login {
            background: #6B8E6B;
            color: white;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 7px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: #557355;
            color: white;
        }

        .daftar {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
        }

        .daftar a {
            color: #557355;
            font-weight: bold;
            text-decoration: none;
        }

        .daftar a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="login">
    <h2 class="judul">KEBUN PINJAM</h2>
    <p class="deskripsi">Penyewaan Alat Berkebun</p>

    <?php if ($error != "") { ?>
        <div class="alert alert-danger py-2" role="alert">
            <?= htmlspecialchars($error); ?>
        </div>
    <?php } ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control"
                   placeholder="Masukkan username" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Masukkan password" required>
        </div>

        <button type="submit" name="login" class="btn btn-login">
            Login
        </button>
    </form>

    <div class="daftar">
        Belum punya akun?
        <a href="daftar.php">Daftar sekarang</a>
    </div>
</div>

</body>
</html>