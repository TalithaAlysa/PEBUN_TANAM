
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'petugas') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";

// Proses konfirmasi pembayaran
if (isset($_POST['proses'])) {
    $id_pembayaran = (int) $_POST['id_pembayaran'];
    $status_baru = $_POST['status'];

    if (!in_array($status_baru, ['lunas', 'ditolak'], true)) {
        $pesan = "Status pembayaran tidak valid!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Periksa pembayaran
            $cek = mysqli_query($koneksi, "
                SELECT status
                FROM pembayaran
                WHERE id_pembayaran = $id_pembayaran
                FOR UPDATE
            ");

            if (!$cek || mysqli_num_rows($cek) == 0) {
                throw new Exception("Data pembayaran tidak ditemukan!");
            }

            $bayar = mysqli_fetch_assoc($cek);

            if ($bayar['status'] != 'menunggu_konfirmasi') {
                throw new Exception("Pembayaran ini sudah diproses!");
            }

            // Ubah status pembayaran
            $ubah = mysqli_query($koneksi, "
                UPDATE pembayaran
                SET status = '$status_baru'
                WHERE id_pembayaran = $id_pembayaran
                  AND status = 'menunggu_konfirmasi'
            ");

            if (!$ubah || mysqli_affected_rows($koneksi) != 1) {
                throw new Exception("Gagal mengubah status pembayaran!");
            }

            mysqli_commit($koneksi);

            header("Location: data_pembayaran.php?pesan=" . $status_baru);
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// Pesan hasil proses
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'lunas') {
        $pesan = "Pembayaran berhasil dikonfirmasi lunas!";
    } elseif ($_GET['pesan'] == 'ditolak') {
        $pesan = "Pembayaran berhasil ditolak!";
    }
}

// Pencarian
$cari = "";
$where = "";

if (isset($_GET['cari']) && $_GET['cari'] != "") {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);

    $where = "WHERE users.nama LIKE '%$cari%'
              OR CAST(pembayaran.id_pembayaran AS CHAR) LIKE '%$cari%'
              OR CAST(peminjaman.id_peminjaman AS CHAR) LIKE '%$cari%'
              OR pembayaran.status LIKE '%$cari%'
              OR pembayaran.metode LIKE '%$cari%'";
}

// Ambil data pembayaran
$data = mysqli_query($koneksi, "
    SELECT
        pembayaran.id_pembayaran,
        pembayaran.id_peminjaman,
        pembayaran.total_bayar,
        pembayaran.metode,
        pembayaran.status AS status_pembayaran,
        pembayaran.tanggal_bayar,
        users.nama
    FROM pembayaran
    JOIN peminjaman
        ON pembayaran.id_peminjaman = peminjaman.id_peminjaman
    JOIN users
        ON peminjaman.id_user = users.id_user
    $where
    ORDER BY pembayaran.id_pembayaran DESC
    LIMIT 100
");

if (!$data) {
    die("Gagal mengambil data pembayaran: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Pembayaran - KEBUN PINJAM</title>

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

    <!-- SIDEBAR -->
    <div class="col-md-3 col-lg-2 sidebar">
        <h4>KEBUN PINJAM</h4>

        <a href="dashboard.php">Dashboard</a>
        <a href="data_peminjaman.php">Data Peminjaman</a>
        <a href="data_pembayaran.php" class="menu-aktif">Data Pembayaran</a>
        <a href="data_pengembalian.php">Data Pengembalian</a>
        <a href="laporan.php">Laporan</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- KONTEN -->
    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul">Data Pembayaran</h3>

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

            <h5 class="judul mb-3">Daftar Pembayaran Peminjam</h5>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-9">
                    <input
                        type="text"
                        name="cari"
                        class="form-control"
                        placeholder="Cari nama, ID, metode, atau status..."
                        value="<?= htmlspecialchars($cari); ?>"
                    >
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-hijau">
                        Cari
                    </button>

                    <a href="data_pembayaran.php" class="btn btn-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>ID Pembayaran</th>
                        <th>ID Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Total Bayar</th>
                        <th>Metode</th>
                        <th>Tanggal Bayar</th>
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

                            <td><?= $row['id_pembayaran']; ?></td>

                            <td><?= $row['id_peminjaman']; ?></td>

                            <td>
                                <?= htmlspecialchars($row['nama']); ?>
                            </td>

                            <td>
                                Rp<?= number_format(
                                    $row['total_bayar'],
                                    0,
                                    ',',
                                    '.'
                                ); ?>
                            </td>

                            <td>
                                <?php
                                if ($row['metode'] == 'dana') {
                                    echo 'DANA / QRIS';
                                } elseif ($row['metode'] == 'cash') {
                                    echo 'Cash';
                                } else {
                                    echo htmlspecialchars($row['metode']);
                                }
                                ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['tanggal_bayar'] ?? '-'); ?>
                            </td>

                            <td>
                                <?php
                                if ($row['status_pembayaran'] == 'menunggu_konfirmasi') {
                                    echo '<span class="badge bg-warning text-dark">
                                            Menunggu Konfirmasi
                                          </span>';
                                } elseif ($row['status_pembayaran'] == 'lunas') {
                                    echo '<span class="badge bg-success">
                                            Lunas
                                          </span>';
                                } elseif ($row['status_pembayaran'] == 'ditolak') {
                                    echo '<span class="badge bg-danger">
                                            Ditolak
                                          </span>';
                                } else {
                                    echo '<span class="badge bg-secondary">'
                                        . htmlspecialchars($row['status_pembayaran'])
                                        . '</span>';
                                }
                                ?>
                            </td>

                            <td>
                                <?php if ($row['status_pembayaran'] == 'menunggu_konfirmasi') { ?>

                                    <form method="POST"
                                          class="d-flex flex-column gap-2"
                                          onsubmit="return confirm('Yakin memproses pembayaran ini? Pastikan pembayaran sudah diperiksa.');">

                                        <input
                                            type="hidden"
                                            name="id_pembayaran"
                                            value="<?= $row['id_pembayaran']; ?>"
                                        >

                                        <button
                                            type="submit"
                                            name="status"
                                            value="lunas"
                                            class="btn btn-sm btn-hijau"
                                            onclick="this.form.proses.value='1'"
                                        >
                                            Konfirmasi Lunas
                                        </button>

                                        <button
                                            type="submit"
                                            name="status"
                                            value="ditolak"
                                            class="btn btn-sm btn-merah"
                                            onclick="this.form.proses.value='1'"
                                        >
                                            Tolak
                                        </button>

                                        <input
                                            type="hidden"
                                            name="proses"
                                            value="1"
                                        >
                                    </form>

                                <?php } else { ?>

                                    <span class="text-secondary">
                                        Sudah diproses
                                    </span>

                                <?php } ?>
                            </td>
                        </tr>
                    <?php
                        }
                    } else {
                    ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                Belum ada data pembayaran.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <small class="text-secondary">
                Menampilkan maksimal 100 data pembayaran terbaru.
            </small>

        </div>
    </div>

</div>
</div>
</body>
</html>