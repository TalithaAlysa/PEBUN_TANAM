
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// Hitung alat yang tersedia
$query_alat = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM alat
    WHERE stok > 0
");
$total_alat = mysqli_fetch_assoc($query_alat)['total'] ?? 0;

// Hitung peminjaman milik pengguna
$query_peminjaman = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE id_user = $id_user
");
$total_peminjaman = mysqli_fetch_assoc($query_peminjaman)['total'] ?? 0;

// Hitung pengajuan yang masih menunggu
$query_menunggu = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE id_user = $id_user
    AND status = 'menunggu'
");
$total_menunggu = mysqli_fetch_assoc($query_menunggu)['total'] ?? 0;

// Hitung peminjaman yang disetujui
$query_disetujui = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE id_user = $id_user
    AND status = 'disetujui'
");
$total_disetujui = mysqli_fetch_assoc($query_disetujui)['total'] ?? 0;

// Ambil peminjaman terbaru milik pengguna
$data_peminjaman = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', d.jumlah, ')')
            SEPARATOR ', '
        ) AS daftar_alat
    FROM peminjaman p
    LEFT JOIN detail_peminjaman d
        ON p.id_peminjaman = d.id_peminjaman
    LEFT JOIN alat a
        ON d.id_alat = a.id_alat
    WHERE p.id_user = $id_user
    GROUP BY p.id_peminjaman
    ORDER BY p.id_peminjaman DESC
    LIMIT 5
");

if (!$data_peminjaman) {
    die("Gagal mengambil data: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Peminjam - KEBUN PINJAM</title>

<link rel="stylesheet" href="../assets/css/bootstrap.min.css">

<style>
body { background: #E8EDE6; }

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

.sidebar a:hover { background: #557355; }

.sidebar a.menu-aktif {
    background: #557355;
    border-left: 4px solid white;
    font-weight: bold;
}

.konten { padding: 30px; }

.kartu {
    background: white;
    border: none;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 3px 10px #00000015;
}

.judul { color: #426447; }

.angka {
    color: #426447;
    font-size: 30px;
    font-weight: bold;
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

@media(max-width:767px) {
    .sidebar { min-height: auto; }
    .konten { padding: 20px 15px; }
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
        <a href="data_alat.php">Data Alat</a>
        <a href="ajukan_peminjaman.php">Ajukan Peminjaman</a>
        <a href="data_peminjaman.php">Data Peminjaman</a>
        <a href="data_pengembalian.php">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- KONTEN -->
    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Dashboard Peminjam</h3>

        <p class="text-secondary mb-4">
            Selamat datang,
            <?= htmlspecialchars($_SESSION['nama'] ?? 'Peminjam'); ?>!
        </p>

        <div class="row g-3 mb-4">

            <div class="col-md-6 col-xl-3">
                <div class="kartu h-100">
                    <p class="text-secondary mb-2">Alat Tersedia</p>
                    <div class="angka"><?= $total_alat; ?></div>
                    <small class="text-secondary">Jenis alat dengan stok tersedia</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kartu h-100">
                    <p class="text-secondary mb-2">Total Peminjaman</p>
                    <div class="angka"><?= $total_peminjaman; ?></div>
                    <small class="text-secondary">Seluruh pengajuanmu</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kartu h-100">
                    <p class="text-secondary mb-2">Menunggu Persetujuan</p>
                    <div class="angka"><?= $total_menunggu; ?></div>
                    <small class="text-secondary">Pengajuan belum diproses</small>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="kartu h-100">
                    <p class="text-secondary mb-2">Sedang Dipinjam</p>
                    <div class="angka"><?= $total_disetujui; ?></div>
                    <small class="text-secondary">Peminjaman yang disetujui</small>
                </div>
            </div>

        </div>

        <div class="kartu mb-4">
            <h5 class="judul mb-3">Peminjaman Terbaru</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Peminjaman</th>
                            <th>Daftar Alat</th>
                            <th>Tanggal Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php
                    $no = 1;

                    if (mysqli_num_rows($data_peminjaman) > 0) {
                        while ($row = mysqli_fetch_assoc($data_peminjaman)) {
                    ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><?= $row['id_peminjaman']; ?></td>
                            <td>
                                <?= htmlspecialchars($row['daftar_alat'] ?? '-'); ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['tanggal_pinjam']); ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($row['tanggal_rencana_kembali']); ?>
                            </td>
                            <td>
                                <?php if ($row['status'] == 'menunggu') { ?>
                                    <span class="badge bg-warning text-dark">Menunggu</span>
                                <?php } elseif ($row['status'] == 'disetujui') { ?>
                                    <span class="badge bg-success">Disetujui</span>
                                <?php } elseif ($row['status'] == 'ditolak') { ?>
                                    <span class="badge bg-danger">Ditolak</span>
                                <?php } elseif ($row['status'] == 'dikembalikan') { ?>
                                    <span class="badge bg-secondary">Dikembalikan</span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($row['status']); ?>
                                    </span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                Kamu belum memiliki peminjaman.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <a href="ajukan_peminjaman.php" class="btn btn-hijau">
                Ajukan Peminjaman
            </a>
        </div>

    </div>
</div>
</div>
</body>
</html>
