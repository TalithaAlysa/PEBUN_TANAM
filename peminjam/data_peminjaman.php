
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$pesan = $_GET['pesan'] ?? "";
$cari = "";
$where = "";

if (isset($_GET['cari']) && $_GET['cari'] != "") {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);

    $where = "AND (
        p.status LIKE '%$cari%'
        OR a.nama_alat LIKE '%$cari%'
        OR CAST(p.id_peminjaman AS CHAR) LIKE '%$cari%'
        OR COALESCE(byr.status, '') LIKE '%$cari%'
        OR COALESCE(p.metode_pembayaran, '') LIKE '%$cari%'
    )";
}

$data = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        p.metode_pembayaran,
        byr.total_bayar,
        byr.metode,
        byr.status AS status_pembayaran,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            ORDER BY a.nama_alat SEPARATOR ', '
        ) AS daftar_alat
    FROM peminjaman p
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a
        ON dp.id_alat = a.id_alat
    LEFT JOIN pembayaran byr
        ON p.id_peminjaman = byr.id_peminjaman
    WHERE p.id_user = $id_user
    $where
    GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        p.metode_pembayaran,
        byr.total_bayar,
        byr.metode,
        byr.status
    ORDER BY p.id_peminjaman DESC
");

if (!$data) {
    die("Gagal mengambil data peminjaman: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Peminjaman - KEBUN PINJAM</title>

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

.judul {
    color: #426447;
}

.kartu {
    background: white;
    border: none;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 3px 10px #00000015;
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

    <div class="col-md-3 col-lg-2 sidebar">
        <h4>KEBUN PINJAM</h4>

        <a href="dashboard.php">Dashboard</a>
        <a href="data_alat.php">Data Alat</a>
        <a href="ajukan_peminjaman.php">Ajukan Peminjaman</a>
        <a href="data_peminjaman.php" class="menu-aktif">Data Peminjaman</a>
        <a href="data_pengembalian.php">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Data Peminjaman Saya</h3>

        <p class="text-secondary mb-4">
            Lihat status peminjaman dan pembayaran sewa alat.
        </p>

        <div class="kartu">

            <?php if ($pesan == "berhasil") { ?>
                <div class="alert alert-success">
                    Pengajuan peminjaman berhasil dikirim.
                    Silakan tunggu persetujuan Petugas sebelum membayar.
                </div>
            <?php } elseif ($pesan == "pembayaran") { ?>
                <div class="alert alert-info">
                    Pembayaran berhasil dikirim dan menunggu konfirmasi Petugas.
                </div>
            <?php } ?>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-9">
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Cari nama alat, ID, atau status..."
                        value="<?= htmlspecialchars($cari); ?>">
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-hijau">
                        Cari
                    </button>

                    <a href="data_peminjaman.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>ID Peminjaman</th>
                            <th>Daftar Alat (Jumlah)</th>
                            <th>Tanggal Pinjam</th>
                            <th>Rencana Kembali</th>
                            <th>Status Peminjaman</th>
                            <th>Total Sewa</th>
                            <th>Metode Pembayaran</th>
                            <th>Status Pembayaran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php
                    $no = 1;

                    if (mysqli_num_rows($data) > 0) {
                        while ($row = mysqli_fetch_assoc($data)) {
                            $status = $row['status'];
                            $status_bayar = $row['status_pembayaran'];

                            if ($status == 'menunggu') {
                                $warna = 'warning text-dark';
                            } elseif ($status == 'disetujui') {
                                $warna = 'success';
                            } elseif ($status == 'ditolak') {
                                $warna = 'danger';
                            } elseif ($status == 'dikembalikan') {
                                $warna = 'secondary';
                            } else {
                                $warna = 'dark';
                            }

                            // Metode yang dipilih saat mengajukan
                            $metode = $row['metode_pembayaran']
                                ?: $row['metode'];
                    ?>
                        <tr>
                            <td><?= $no++; ?></td>

                            <td>
                                <?= (int) $row['id_peminjaman']; ?>
                            </td>

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
                                <span class="badge bg-<?= $warna; ?>">
                                    <?php
                                    if ($status == 'menunggu') {
                                        echo 'Menunggu Persetujuan';
                                    } elseif ($status == 'disetujui') {
                                        echo 'Disetujui';
                                    } elseif ($status == 'ditolak') {
                                        echo 'Ditolak';
                                    } elseif ($status == 'dikembalikan') {
                                        echo 'Dikembalikan';
                                    } else {
                                        echo htmlspecialchars(ucfirst($status));
                                    }
                                    ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($row['total_bayar'] !== null) { ?>
                                    Rp<?= number_format(
                                        (float) $row['total_bayar'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </td>

                            <td>
                                <?php
                                if ($metode == 'cash') {
                                    echo 'Cash';
                                } elseif ($metode == 'dana') {
                                    echo 'DANA';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>

                            <td>
                                <?php if ($status == 'menunggu') { ?>

                                    <span class="badge bg-warning text-dark">
                                        Menunggu Persetujuan
                                    </span>

                                <?php } elseif ($status == 'ditolak') { ?>

                                    <span class="badge bg-secondary">
                                        Tidak Dapat Membayar
                                    </span>

                                <?php } elseif ($status_bayar == 'menunggu_konfirmasi') { ?>

                                    <span class="badge bg-warning text-dark">
                                        Menunggu Konfirmasi
                                    </span>

                                <?php } elseif ($status_bayar == 'lunas') { ?>

                                    <span class="badge bg-success">
                                        Lunas
                                    </span>

                                <?php } elseif ($status_bayar == 'ditolak') { ?>

                                    <span class="badge bg-danger">
                                        Pembayaran Ditolak
                                    </span>

                                <?php } elseif ($status == 'disetujui') { ?>

                                    <span class="badge bg-secondary">
                                        Belum Bayar
                                    </span>

                                <?php } else { ?>

                                    <span class="badge bg-secondary">-</span>

                                <?php } ?>
                            </td>

                            <td>
                                <?php if (
                                    $status == 'disetujui' &&
                                    ($status_bayar === null || $status_bayar == 'ditolak')
                                ) { ?>

                                    <a
                                        href="pembayaran.php?id=<?= (int) $row['id_peminjaman']; ?>"
                                        class="btn btn-hijau btn-sm">
                                        Bayar
                                    </a>

                                <?php } elseif ($status == 'menunggu') { ?>

                                    <span class="text-secondary">
                                        Menunggu
                                    </span>

                                <?php } elseif ($status == 'ditolak') { ?>

                                    <span class="text-danger">
                                        Pengajuan Ditolak
                                    </span>

                                <?php } elseif ($status_bayar == 'menunggu_konfirmasi') { ?>

                                    <span class="text-secondary">
                                        Menunggu
                                    </span>

                                <?php } elseif ($status_bayar == 'lunas') { ?>

                                    <span class="text-success">
                                        Sudah Bayar
                                    </span>

                                <?php } else { ?>

                                    -

                                <?php } ?>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="10" class="text-center">
                                Kamu belum memiliki data peminjaman.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <a href="ajukan_peminjaman.php" class="btn btn-hijau">
                + Ajukan Peminjaman
            </a>

        </div>
    </div>

</div>
</div>
</body>
</html>
