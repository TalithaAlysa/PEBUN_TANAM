
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";

// Proses persetujuan atau penolakan peminjaman
if (isset($_POST['proses'])) {
    $id_peminjaman = (int) $_POST['id_peminjaman'];
    $status_baru = $_POST['status'];

    if (!in_array($status_baru, ['disetujui', 'ditolak'], true)) {
        $pesan = "Status peminjaman tidak valid!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_query($koneksi, "
                SELECT status
                FROM peminjaman
                WHERE id_peminjaman = $id_peminjaman
                FOR UPDATE
            ");

            if (!$cek || mysqli_num_rows($cek) == 0) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $pinjam = mysqli_fetch_assoc($cek);

            if ($pinjam['status'] != 'menunggu') {
                throw new Exception("Pengajuan ini sudah diproses!");
            }

            // Kurangi stok jika peminjaman disetujui
            if ($status_baru == 'disetujui') {
                $detail = mysqli_query($koneksi, "
                    SELECT id_alat, SUM(jumlah) AS jumlah
                    FROM detail_peminjaman
                    WHERE id_peminjaman = $id_peminjaman
                    GROUP BY id_alat
                ");

                if (!$detail || mysqli_num_rows($detail) == 0) {
                    throw new Exception("Detail alat peminjaman tidak ditemukan!");
                }

                while ($d = mysqli_fetch_assoc($detail)) {
                    $id_alat = (int) $d['id_alat'];
                    $jumlah = (int) $d['jumlah'];

                    $stok = mysqli_query($koneksi, "
                        UPDATE alat
                        SET stok = stok - $jumlah
                        WHERE id_alat = $id_alat
                          AND stok >= $jumlah
                    ");

                    if (!$stok || mysqli_affected_rows($koneksi) != 1) {
                        throw new Exception(
                            "Stok alat tidak mencukupi. Pengajuan tidak dapat disetujui."
                        );
                    }
                }
            }

            $ubah = mysqli_query($koneksi, "
                UPDATE peminjaman
                SET status = '$status_baru'
                WHERE id_peminjaman = $id_peminjaman
            ");

            if (!$ubah) {
                throw new Exception("Gagal mengubah status peminjaman!");
            }

            mysqli_commit($koneksi);

            header("Location: data_peminjaman.php?pesan=" . $status_baru);
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// Pesan hasil proses
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'disetujui') {
        $pesan = "Peminjaman berhasil disetujui!";
    } elseif ($_GET['pesan'] == 'ditolak') {
        $pesan = "Pengajuan peminjaman berhasil ditolak!";
    }
}

// Pencarian
$cari = "";
$where = "";

if (isset($_GET['cari']) && $_GET['cari'] != "") {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);

    $where = "WHERE users.nama LIKE '%$cari%'
              OR CAST(peminjaman.id_peminjaman AS CHAR) LIKE '%$cari%'
              OR peminjaman.status LIKE '%$cari%'
              OR pembayaran.status LIKE '%$cari%'";
}

// Ambil data peminjaman beserta status pembayaran
$data = mysqli_query($koneksi, "
    SELECT
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.status,
        pembayaran.status AS status_pembayaran,
        pembayaran.total_bayar,
        GROUP_CONCAT(
            CONCAT(alat.nama_alat, ' (', detail_peminjaman.jumlah, ')')
            SEPARATOR ', '
        ) AS daftar_alat
    FROM peminjaman
    JOIN users
        ON peminjaman.id_user = users.id_user
    LEFT JOIN detail_peminjaman
        ON peminjaman.id_peminjaman = detail_peminjaman.id_peminjaman
    LEFT JOIN alat
        ON detail_peminjaman.id_alat = alat.id_alat
    LEFT JOIN pembayaran
        ON peminjaman.id_peminjaman = pembayaran.id_peminjaman
    $where
    GROUP BY
        peminjaman.id_peminjaman,
        users.nama,
        peminjaman.tanggal_pinjam,
        peminjaman.tanggal_rencana_kembali,
        peminjaman.status,
        pembayaran.status,
        pembayaran.total_bayar
    ORDER BY peminjaman.id_peminjaman DESC
    LIMIT 100
");

if (!$data) {
    die("Gagal mengambil data: " . mysqli_error($koneksi));
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

.btn-merah {
    background: #C96B6B;
    color: white;
    border: none;
}

.btn-merah:hover {
    background: #B85A5A;
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

        <a href="dashboard.php" >Dashboard</a>
        <a href="data_peminjaman.php"  class="menu-aktif">Data Peminjaman</a>
        <a href="data_pembayaran.php">Data Pembayaran</a>
        <a href="data_pengembalian.php">Data Pengembalian</a>
        <a href="laporan.php">Laporan</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Data Peminjaman</h3>

        <p class="text-secondary mb-4">
            Selamat datang,
            <?= htmlspecialchars($_SESSION['nama'] ?? 'Petugas'); ?>!
        </p>

        <?php if ($pesan != "") { ?>
            <div class="alert alert-info">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php } ?>

        <div class="kartu">

            <h5 class="judul mb-3">Daftar Pengajuan Peminjaman</h5>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-9">
                    <input type="text"
                           name="cari"
                           class="form-control"
                           placeholder="Cari nama, ID, atau status..."
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
                        <th>Nama Peminjam</th>
                        <th>Alat</th>
                        <th>Tanggal Pinjam</th>
                        <th>Rencana Kembali</th>
                        <th>Status Peminjaman</th>
                        <th>Status Pembayaran</th>
                        <th>Aksi</th>
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

                            <td><?= $row['id_peminjaman']; ?></td>

                            <td>
                                <?= htmlspecialchars($row['nama']); ?>
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
                                <?php if ($row['status'] == 'menunggu') { ?>
                                    <span class="badge bg-warning text-dark">
                                        Menunggu
                                    </span>
                                <?php } elseif ($row['status'] == 'disetujui') { ?>
                                    <span class="badge bg-success">
                                        Disetujui
                                    </span>
                                <?php } elseif ($row['status'] == 'ditolak') { ?>
                                    <span class="badge bg-danger">
                                        Ditolak
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars(ucfirst($row['status'])); ?>
                                    </span>
                                <?php } ?>
                            </td>

                            <td>
                                <?php
                                if ($row['status'] == 'ditolak') {
                                    echo '<span class="text-secondary">-</span>';
                                } elseif ($row['status_pembayaran'] == 'menunggu_konfirmasi') {
                                    echo '<span class="badge bg-warning text-dark">Menunggu Konfirmasi</span>';
                                } elseif ($row['status_pembayaran'] == 'lunas') {
                                    echo '<span class="badge bg-success">Lunas</span>';
                                } elseif ($row['status_pembayaran'] == 'ditolak') {
                                    echo '<span class="badge bg-danger">Pembayaran Ditolak</span>';
                                } elseif ($row['status'] == 'disetujui') {
                                    echo '<span class="badge bg-secondary">Belum Membayar</span>';
                                } else {
                                    echo '<span class="text-secondary">-</span>';
                                }
                                ?>
                            </td>

                            <td>
                                <?php if ($row['status'] == 'menunggu') { ?>

                                    <form method="POST"
                                          class="d-flex flex-column gap-2"
                                          onsubmit="return confirm('Yakin memproses pengajuan ini?');">

                                        <input type="hidden"
                                               name="id_peminjaman"
                                               value="<?= $row['id_peminjaman']; ?>">

                                        <button type="submit"
                                                name="proses"
                                                value="1"
                                                class="btn btn-sm btn-hijau"
                                                onclick="this.form.status.value='disetujui'">
                                            Setujui
                                        </button>

                                        <button type="submit"
                                                name="proses"
                                                value="1"
                                                class="btn btn-sm btn-merah"
                                                onclick="this.form.status.value='ditolak'">
                                            Tolak
                                        </button>

                                        <input type="hidden" name="status" value="">
                                    </form>

                                <?php } elseif (
                                    $row['status_pembayaran'] == 'menunggu_konfirmasi'
                                    || $row['status_pembayaran'] == 'ditolak'
                                ) { ?>

                                    <a href="data_pembayaran.php"
                                       class="btn btn-sm btn-hijau">
                                        Periksa Pembayaran
                                    </a>

                                <?php } else { ?>

                                    <span class="text-secondary">-</span>

                                <?php } ?>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                Belum ada data peminjaman.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <small class="text-secondary">
                Menampilkan maksimal 100 data peminjaman terbaru.
            </small>

        </div>
    </div>

</div>
</div>
</body>
</html>