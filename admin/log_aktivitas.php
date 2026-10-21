
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$halaman = 'log_aktivitas';

$cari = "";
$where = "";

if (isset($_GET['cari']) && $_GET['cari'] != "") {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);

    $where = "WHERE u.nama LIKE '%$cari%'
              OR u.level LIKE '%$cari%'
              OR l.aktivitas LIKE '%$cari%'";
}

$query = mysqli_query($koneksi, "
    SELECT l.id_log, l.id_user, l.aktivitas, l.waktu,
           u.nama, u.level
    FROM log_aktivitas l
    LEFT JOIN users u ON l.id_user = u.id_user
    $where
    ORDER BY l.id_log DESC
    LIMIT 100
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log Aktivitas - KEBUN PINJAM</title>
<link rel="stylesheet" href="../assets/css/bootstrap.min.css">

<style>
body { background: #E8EDE6; }
.sidebar { background: #6B8E6B; min-height: 100vh; padding: 25px 15px; }
.sidebar h4 { color: white; font-weight: bold; margin-bottom: 30px; }
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
@media(max-width:767px) {
    .sidebar { min-height: auto; }
    .konten { padding: 20px 15px; }
}
</style>
</head>

<body>
<div class="container-fluid">
<div class="row">

<div class="col-md-3 col-lg-2 sidebar">
    <h4>KEBUN PINJAM</h4>
    <a href="dashboard.php">Dashboard</a>
    <a href="kategori.php">Data Kategori</a>
    <a href="data_alat.php">Data Alat</a>
    <a href="data_pengguna.php">Data Pengguna</a>
    <a href="data_peminjaman.php">Data Penyewaan</a>
    <a href="data_pengembalian.php">Data Pengembalian</a>
    <a href="laporan.php">Laporan</a>
    <a href="log_aktivitas.php" class="menu-aktif">Log Aktivitas</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="col-md-9 col-lg-10 konten">

    <h3 class="judul">Log Aktivitas</h3>
    <p class="text-secondary mb-4">
        Selamat datang, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>!
    </p>

    <div class="kartu">
        <h5 class="judul mb-3">Riwayat Aktivitas Pengguna</h5>

        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-9">
                <input type="text" name="cari" class="form-control"
                       placeholder="Cari nama, level, atau aktivitas..."
                       value="<?= htmlspecialchars($cari); ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-success">Cari</button>
                <a href="log_aktivitas.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-success">
                <tr>
                    <th>No</th>
                    <th>Nama Pengguna</th>
                    <th>Level</th>
                    <th>Aktivitas</th>
                    <th>Waktu</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $no = 1;

            if ($query && mysqli_num_rows($query) > 0) {
                while ($data = mysqli_fetch_assoc($query)) {
            ?>
                <tr>
                    <td><?= $no++; ?></td>
                    <td>
                        <?= htmlspecialchars($data['nama'] ?? 'Pengguna tidak ditemukan'); ?>
                    </td>
                    <td>
                        <?php if (!empty($data['level'])) { ?>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars(ucfirst($data['level'])); ?>
                            </span>
                        <?php } else { ?>
                            -
                        <?php } ?>
                    </td>
                    <td><?= htmlspecialchars($data['aktivitas']); ?></td>
                    <td><?= htmlspecialchars($data['waktu']); ?></td>
                </tr>
            <?php
                }
            } else {
            ?>
                <tr>
                    <td colspan="5" class="text-center">
                        Belum ada aktivitas yang tercatat.
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        </div>

        <small class="text-secondary">
            Menampilkan maksimal 100 aktivitas terbaru dari semua pengguna.
        </small>
    </div>

</div>
</div>
</div>
</body>
</html>