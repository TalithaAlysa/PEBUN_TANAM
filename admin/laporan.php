<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$tanggal_awal = $_GET['tanggal_awal'] ?? "";
$tanggal_akhir = $_GET['tanggal_akhir'] ?? "";

$sql = "SELECT
            p.id_peminjaman,
            u.nama AS nama_peminjam,
            p.tanggal_pinjam,
            pg.tanggal_kembali,
            p.status,
            GROUP_CONCAT(DISTINCT a.nama_alat SEPARATOR ', ') AS nama_alat,
            COALESCE(SUM(
                d.jumlah * a.tarif_sewa *
                GREATEST(DATEDIFF(
                    COALESCE(pg.tanggal_kembali, p.tanggal_pinjam),
                    p.tanggal_pinjam
                ), 1)
            ), 0) AS biaya_sewa,
            COALESCE(MAX(pg.denda), 0) AS denda
        FROM peminjaman p
        JOIN users u ON p.id_user = u.id_user
        LEFT JOIN detail_peminjaman d ON p.id_peminjaman = d.id_peminjaman
        LEFT JOIN alat a ON d.id_alat = a.id_alat
        LEFT JOIN pengembalian pg ON p.id_peminjaman = pg.id_peminjaman
        WHERE 1=1";

if ($tanggal_awal != "" && $tanggal_akhir != "") {
    $awal = mysqli_real_escape_string($koneksi, $tanggal_awal);
    $akhir = mysqli_real_escape_string($koneksi, $tanggal_akhir);

    $sql .= " AND DATE(p.tanggal_pinjam) BETWEEN '$awal' AND '$akhir'";
}

$sql .= " GROUP BY
            p.id_peminjaman,
            u.nama,
            p.tanggal_pinjam,
            pg.tanggal_kembali,
            p.status
          ORDER BY p.id_peminjaman DESC";

$data = mysqli_query($koneksi, $sql);

if (!$data) {
    die("Gagal mengambil laporan: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laporan Peminjaman - KEBUN PINJAM</title>

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

        .judul {
            color: #426447;
        }

        .btn-hijau {
            background: #6B8E6B;
            color: white;
            border: none;
        }

        .btn-hijau:hover {
            background: #557355;
            color: white;
        }

        .table th {
            background: #6B8E6B;
            color: white;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        @media (max-width: 767px) {
            .sidebar {
                min-height: auto;
            }

            .konten {
                padding: 20px 15px;
            }
        }

        @media print {
            .sidebar, .filter, .btn-cetak {
                display: none !important;
            }

            .konten {
                padding: 0 !important;
                width: 100% !important;
            }

            body {
                background: white !important;
            }

            table {
                font-size: 11px;
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

            <a href="dashboard.php">Dashboard</a>
            <a href="kategori.php">Data Kategori</a>
            <a href="data_alat.php">Data Alat</a>
            <a href="data_pengguna.php">Data Pengguna</a>
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php" class="menu-aktif">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="judul">Laporan Peminjaman</h3>

                <button onclick="window.print()" class="btn btn-hijau btn-cetak">
                    Cetak Laporan
                </button>
            </div>

            <p class="text-secondary mb-4">
                Selamat datang,
                <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>!
            </p>

            <!-- FILTER TANGGAL -->
            <div class="kartu mb-4 filter">
                <form method="GET" class="row g-3 align-items-end">

                    <div class="col-md-4">
                        <label class="form-label">Tanggal Awal</label>
                        <input type="date" name="tanggal_awal"
                            class="form-control"
                            value="<?= htmlspecialchars($tanggal_awal) ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="tanggal_akhir"
                            class="form-control"
                            value="<?= htmlspecialchars($tanggal_akhir) ?>">
                    </div>

                    <div class="col-md-4">
                        <button type="submit" class="btn btn-hijau">Filter</button>
                        <a href="laporan.php" class="btn btn-secondary">Reset</a>
                    </div>

                </form>
            </div>

            <!-- TABEL LAPORAN -->
            <div class="kartu">
                <h5 class="judul mb-3">Daftar Laporan Peminjaman</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle mb-0">

                        <thead>
                            <tr>
                                <th>No</th>
                                <th>ID Peminjaman</th>
                                <th>Nama Peminjam</th>
                                <th>Alat</th>
                                <th>Tanggal Pinjam</th>
                                <th>Tanggal Kembali</th>
                                <th>Biaya Sewa</th>
                                <th>Denda</th>
                                <th>Total Biaya</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $no = 1;
                            $total_semua = 0;

                            if (mysqli_num_rows($data) > 0) {
                                while ($row = mysqli_fetch_assoc($data)) {
                                    $biaya_sewa = (float) $row['biaya_sewa'];
                                    $denda = (float) $row['denda'];
                                    $total = $biaya_sewa + $denda;
                                    $total_semua += $total;
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>
                                    <td><?= htmlspecialchars($row['id_peminjaman']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_peminjam']); ?></td>
                                    <td><?= htmlspecialchars($row['nama_alat'] ?? '-'); ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_pinjam']); ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_kembali'] ?? '-'); ?></td>
                                    <td>Rp<?= number_format($biaya_sewa, 0, ',', '.'); ?></td>
                                    <td>Rp<?= number_format($denda, 0, ',', '.'); ?></td>
                                    <td>Rp<?= number_format($total, 0, ',', '.'); ?></td>
                                    <td><?= htmlspecialchars($row['status']); ?></td>
                                </tr>
                            <?php
                                }
                            ?>
                                <tr>
                                    <th colspan="8" class="text-end">Total Keseluruhan</th>
                                    <th colspan="2">
                                        Rp<?= number_format($total_semua, 0, ',', '.'); ?>
                                    </th>
                                </tr>
                            <?php
                            } else {
                            ?>
                                <tr>
                                    <td colspan="10" class="text-center">
                                        Belum ada data peminjaman.
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>

                    </table>
                </div>

            </div>

        </div>
    </div>
</div>

</body>
</html>