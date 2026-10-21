<?php
include "koneksi.php";

$pesan = "";

if (isset($_POST['daftar'])) {
    $nama = mysqli_real_escape_string($koneksi, $_POST['nama']);
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    $password = md5($_POST['password']);
    $konfirmasi = md5($_POST['konfirmasi']);

    if ($password != $konfirmasi) {
        $pesan = "Konfirmasi password tidak sama!";
    } else {
        $cek = mysqli_query($koneksi, "SELECT * FROM users
                                      WHERE username='$username'");

        if (mysqli_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan!";
        } else {
            $simpan = mysqli_query($koneksi, "INSERT INTO users
                (nama, username, password, level, status)
                VALUES
                ('$nama', '$username', '$password', 'peminjam', 'aktif')");

            if ($simpan) {
                echo "<script>
                    alert('Pendaftaran berhasil!');
                    window.location='index.php';
                </script>";
                exit;
            } else {
                $pesan = "Pendaftaran gagal!";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daftar PEKEBUN</title>

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

        .daftar {
            background: white;
            width: 100%;
            max-width: 430px;
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

        .btn-daftar {
            background: #6B8E6B;
            color: white;
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 7px;
            font-weight: 600;
        }

        .btn-daftar:hover {
            background: #557355;
            color: white;
        }

        .login {
            text-align: center;
            margin-top: 20px;
            margin-bottom: 0;
            font-size: 14px;
        }

        .login a {
            color: #557355;
            font-weight: bold;
            text-decoration: none;
        }

        .login a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="daftar">
    <h2 class="judul">Daftar PEKEBUN</h2>
    <p class="deskripsi">Buat akun untuk mulai meminjam alat berkebun</p>

    <?php if ($pesan != "") { ?>
        <div class="alert alert-danger py-2" role="alert">
            <?= htmlspecialchars($pesan); ?>
        </div>
    <?php } ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control"
                   placeholder="Masukkan nama lengkap" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control"
                   placeholder="Buat username" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control"
                   placeholder="Buat password" required>
        </div>

        <div class="mb-4">
            <label class="form-label">Konfirmasi Password</label>
            <input type="password" name="konfirmasi" class="form-control"
                   placeholder="Ulangi password" required>
        </div>

        <button type="submit" name="daftar" class="btn btn-daftar">
            Daftar
        </button>
    </form>

    <p class="login">
        Sudah punya akun? <a href="index.php">Login</a>
    </p>
</div>

</body>
</html>