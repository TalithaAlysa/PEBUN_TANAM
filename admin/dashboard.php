
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$halaman = 'dashboard';

// Menghitung jumlah data
$alat = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM alat");
$data_alat = mysqli_fetch_assoc($alat);

$kategori = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM kategori");
$data_kategori = mysqli_fetch_assoc($kategori);

$peminjaman = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM peminjaman");
$data_peminjaman = mysqli_fetch_assoc($peminjaman);

$pengguna = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM users");
$data_pengguna = mysqli_fetch_assoc($pengguna);

$pengembalian = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pengembalian");
$data_pengembalian = mysqli_fetch_assoc($pengembalian);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Dashboard Admin - KEBUN PINJAM</title>

    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">

    <style>
        body {
            background: #E8EDE6;
        }

        .sidebar {
            background: #6B8E6B;
            min-height: 100vh;
            padding: 25px 15px;
        }

        .sidebar h4 {
            color: white;
            font-weight: bold;
            margin-bottom: 30px;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 11px 13px;
            border-radius: 7px;
            margin-bottom: 8px;
            border-left: 4px solid transparent;
        }

        .sidebar a:hover {
            background: #557355;
        }

        .sidebar a.menu-aktif {
            background: #557355;
            border-left: 4px solid white;
            font-weight: bold;
        }

        .konten {
            padding: 30px;
        }

        .kartu {
            background: white;
            border: none;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 3px 10px #00000015;
        }

        .angka {
            color: #557355;
            font-size: 30px;
            font-weight: bold;
        }

        .judul {
            color: #426447;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
            }

            .konten {
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body>

<div class="container-fluid">
    <div class="row">

        <!-- SIDEBAR -->
        <div class="col-md-3 col-lg-2 sidebar">
            <h4>KEBUN PINJAM</h4>

            <a href="dashboard.php" class="menu-aktif">Dashboard</a>
            <a href="kategori.php">Data Kategori</a>
            <a href="data_alat.php">Data Alat</a>
            <a href="data_pengguna.php">Data Pengguna</a>
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Dashboard Admin</h3>

            <p class="text-secondary">
                Selamat datang,
                <?= htmlspecialchars($_SESSION['nama']); ?>!
            </p>

            <!-- RINGKASAN DATA -->
            <div class="row g-3 mt-3">

                <div class="col-md-6 col-xl-4">
                    <div class="kartu">
                        <p>Total Alat</p>
                        <div class="angka">
                            <?= $data_alat['total']; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="kartu">
                        <p>Total Kategori</p>
                        <div class="angka">
                            <?= $data_kategori['total']; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="kartu">
                        <p>Total Penyewaan</p>
                        <div class="angka">
                            <?= $data_peminjaman['total']; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="kartu">
                        <p>Total Pengguna</p>
                        <div class="angka">
                            <?= $data_pengguna['total']; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="kartu">
                        <p>Total Pengembalian</p>
                        <div class="angka">
                            <?= $data_pengembalian['total']; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- INFORMASI SISTEM -->
            <div class="kartu mt-4">
                <h5 class="judul">Informasi Sistem</h5>

                <p class="mb-0">
                    KEBUN PINJAM merupakan aplikasi penyewaan alat
                    berkebun yang membantu pengelolaan data alat,
                    kategori, pengguna, penyewaan, dan pengembalian.
                </p>
            </div>

        </div>
    </div>
</div>

</body>
</html>
