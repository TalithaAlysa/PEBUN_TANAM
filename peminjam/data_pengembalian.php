
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];

// Ambil peminjaman yang masih aktif
$data_pinjam = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        pg.status AS status_pengembalian,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            ORDER BY a.nama_alat SEPARATOR ', '
        ) AS daftar_alat
    FROM peminjaman p
    LEFT JOIN pengembalian pg
        ON p.id_peminjaman = pg.id_peminjaman
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a
        ON dp.id_alat = a.id_alat
    WHERE p.id_user = $id_user
      AND p.status = 'disetujui'
    GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        pg.status
    ORDER BY p.id_peminjaman DESC
");

if (!$data_pinjam) {
    die("Gagal mengambil data peminjaman: " . mysqli_error($koneksi));
}

// Ambil riwayat pengembalian
$data_kembali = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        pg.tanggal_kembali,
        pg.terlambat_hari,
        pg.denda,
        pg.kondisi_alat,
        pg.catatan,
        pg.status,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            ORDER BY a.nama_alat SEPARATOR ', '
        ) AS daftar_alat
    FROM pengembalian pg
    JOIN peminjaman p
        ON pg.id_peminjaman = p.id_peminjaman
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a
        ON dp.id_alat = a.id_alat
    WHERE p.id_user = $id_user
    GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        pg.tanggal_kembali,
        pg.terlambat_hari,
        pg.denda,
        pg.kondisi_alat,
        pg.catatan,
        pg.status
    ORDER BY p.id_peminjaman DESC
");

if (!$data_kembali) {
    die("Gagal mengambil riwayat pengembalian: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengembalian - KEBUN PINJAM</title>

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
    margin-bottom: 25px;
    box-shadow: 0 3px 10px #00000015;
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
        <a href="data_peminjaman.php">Data Peminjaman</a>
        <a href="data_pengembalian.php" class="menu-aktif">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Data Pengembalian</h3>

        <p class="text-secondary mb-4">
            Lihat alat yang masih dipinjam dan pantau riwayat pengembalian.
        </p>

        <div class="kartu">
            <h5 class="judul mb-3">Alat yang Masih Dipinjam</h5>

            <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>ID Peminjaman</th>
                    <th>Daftar Alat (Jumlah)</th>
                    <th>Tanggal Pinjam</th>
                    <th>Rencana Kembali</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;

                if (mysqli_num_rows($data_pinjam) > 0) {
                    while ($row = mysqli_fetch_assoc($data_pinjam)) {
                        $status_pg = $row['status_pengembalian'];
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
                        <?php if ($status_pg == 'menunggu') { ?>
                            <span class="badge bg-warning text-dark">
                                Menunggu Konfirmasi
                            </span>
                        <?php } elseif ($status_pg == 'disetujui') { ?>
                            <span class="badge bg-success">
                                Disetujui
                            </span>
                        <?php } elseif ($status_pg == 'ditolak') { ?>
                            <span class="badge bg-danger">
                                Ditolak
                            </span>
                        <?php } else { ?>
                            <span class="badge bg-secondary">
                                Masih Dipinjam
                            </span>
                        <?php } ?>
                    </td>

                    <td>
                        <?php if ($status_pg == 'menunggu') { ?>
                            <span class="text-warning">
                                Menunggu pemeriksaan Petugas
                            </span>
                        <?php } elseif ($status_pg == 'disetujui') { ?>
                            <span class="text-success">
                                Pengembalian selesai
                            </span>
                        <?php } elseif ($status_pg == 'ditolak') { ?>
                            <span class="text-danger">
                                Hubungi Petugas/Admin
                            </span>
                        <?php } else { ?>
                            <span class="text-secondary">
                                Serahkan alat ke Petugas/Admin
                            </span>
                        <?php } ?>
                    </td>
                </tr>

                <?php
                    }
                } else {
                ?>
                <tr>
                    <td colspan="7" class="text-center">
                        Tidak ada alat yang sedang dipinjam.
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
            </div>

            <div class="alert alert-secondary mb-0">
                Untuk mengembalikan alat, silakan serahkan langsung kepada
                Petugas atau Admin. Petugas akan memeriksa kondisi alat,
                mencatat tanggal pengembalian, dan menghitung denda jika ada.
            </div>
        </div>

        <div class="kartu">
            <h5 class="judul mb-3">Riwayat Pengembalian</h5>

            <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                <tr>
                    <th>No</th>
                    <th>ID Peminjaman</th>
                    <th>Daftar Alat (Jumlah)</th>
                    <th>Tanggal Pinjam</th>
                    <th>Rencana Kembali</th>
                    <th>Tanggal Kembali</th>
                    <th>Terlambat</th>
                    <th>Denda</th>
                    <th>Kondisi Alat</th>
                    <th>Status</th>
                </tr>
                </thead>

                <tbody>
                <?php
                $no = 1;

                if (mysqli_num_rows($data_kembali) > 0) {
                    while ($row = mysqli_fetch_assoc($data_kembali)) {
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
                        <?= $row['status'] == 'disetujui'
                            ? htmlspecialchars($row['tanggal_kembali'] ?? '-')
                            : '-'; ?>
                    </td>

                    <td>
                        <?= $row['status'] == 'disetujui'
                            ? (int) $row['terlambat_hari'] . ' hari'
                            : '-'; ?>
                    </td>

                    <td>
                        <?= $row['status'] == 'disetujui'
                            ? 'Rp' . number_format((float) $row['denda'], 0, ',', '.')
                            : '-'; ?>
                    </td>

                    <td>
                        <?= $row['status'] == 'disetujui'
                            ? htmlspecialchars($row['kondisi_alat'] ?? '-')
                            : '-'; ?>
                    </td>

                    <td>
                        <?php if ($row['status'] == 'menunggu') { ?>
                            <span class="badge bg-warning text-dark">
                                Menunggu Konfirmasi
                            </span>
                        <?php } elseif ($row['status'] == 'disetujui') { ?>
                            <span class="badge bg-success">
                                Dikembalikan
                            </span>
                        <?php } else { ?>
                            <span class="badge bg-danger">
                                Ditolak
                            </span>
                        <?php } ?>
                    </td>
                </tr>

                <?php
                    }
                } else {
                ?>
                <tr>
                    <td colspan="10" class="text-center">
                        Belum ada riwayat pengembalian.
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
