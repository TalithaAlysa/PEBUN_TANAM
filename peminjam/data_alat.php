
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$cari = "";
$where = "";

if (isset($_GET['cari']) && $_GET['cari'] != "") {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);

    $where = "WHERE alat.nama_alat LIKE '%$cari%'
              OR kategori.nama_kategori LIKE '%$cari%'
              OR alat.kondisi LIKE '%$cari%'";
}

$data = mysqli_query($koneksi, "
    SELECT
        alat.id_alat,
        alat.nama_alat,
        kategori.nama_kategori,
        alat.tarif_sewa,
        alat.stok,
        alat.kondisi
    FROM alat
    LEFT JOIN kategori
        ON alat.id_kategori = kategori.id_kategori
    $where
    ORDER BY alat.id_alat DESC
");

if (!$data) {
    die("Gagal mengambil data alat: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Alat - KEBUN PINJAM</title>

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

        <a href="dashboard.php">Dashboard</a>
        <a href="data_alat.php" class="menu-aktif">Data Alat</a>
        <a href="ajukan_peminjaman.php">Ajukan Peminjaman</a>
        <a href="data_peminjaman.php">Data Peminjaman</a>
        <a href="data_pengembalian.php">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- KONTEN -->
    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Data Alat Berkebun</h3>

        <p class="text-secondary mb-4">
            Selamat datang,
            <?= htmlspecialchars($_SESSION['nama'] ?? 'Peminjam'); ?>!
        </p>

        <div class="kartu">

            <h5 class="judul mb-3">Daftar Alat yang Tersedia</h5>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-9">
                    <input type="text" name="cari" class="form-control"
                           placeholder="Cari nama alat, kategori, atau kondisi..."
                           value="<?= htmlspecialchars($cari); ?>">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-hijau">Cari</button>
                    <a href="data_alat.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Alat</th>
                            <th>Kategori</th>
                            <th>Tarif Sewa</th>
                            <th>Stok</th>
                            <th>Kondisi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php
                    $no = 1;

                    if (mysqli_num_rows($data) > 0) {
                        while ($row = mysqli_fetch_assoc($data)) {
                    ?>
                        <tr>
                            <td><?= $no++; ?></td>

                            <td>
                                <?= htmlspecialchars($row['nama_alat']); ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['nama_kategori'] ?? '-'); ?>
                            </td>

                            <td>
                                Rp<?= number_format((float)$row['tarif_sewa'], 0, ',', '.'); ?>
                            </td>

                            <td>
                                <?php if ($row['stok'] > 0) { ?>
                                    <span class="badge bg-success">
                                        <?= (int)$row['stok']; ?> tersedia
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-danger">Habis</span>
                                <?php } ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['kondisi']); ?>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                Data alat tidak ditemukan.
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
