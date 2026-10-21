
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$id_peminjaman = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$pesan = "";

// Ambil data peminjaman milik pengguna
$query = mysqli_query($koneksi, "
    SELECT *
    FROM peminjaman
    WHERE id_peminjaman = $id_peminjaman
      AND id_user = $id_user
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) == 0) {
    die("Data peminjaman tidak ditemukan.");
}

$pinjam = mysqli_fetch_assoc($query);

// Pembayaran hanya tersedia setelah peminjaman disetujui
if ($pinjam['status'] != 'disetujui') {
    die("Pembayaran hanya dapat dilakukan setelah peminjaman disetujui Petugas.");
}

$metode = $pinjam['metode_pembayaran'];

if (!in_array($metode, ['cash', 'dana'], true)) {
    die("Metode pembayaran tidak valid. Silakan hubungi Petugas.");
}

// Hitung lama sewa, minimal 1 hari
$tanggal_pinjam = new DateTime($pinjam['tanggal_pinjam']);
$tanggal_kembali = new DateTime($pinjam['tanggal_rencana_kembali']);
$selisih = $tanggal_pinjam->diff($tanggal_kembali)->days;
$lama_sewa = max(1, $selisih + 1);

// Hitung total pembayaran
$data_alat = mysqli_query($koneksi, "
    SELECT a.nama_alat, a.tarif_sewa, dp.jumlah
    FROM detail_peminjaman dp
    JOIN alat a ON dp.id_alat = a.id_alat
    WHERE dp.id_peminjaman = $id_peminjaman
");

if (!$data_alat) {
    die("Gagal mengambil detail alat: " . mysqli_error($koneksi));
}

$total_bayar = 0;
$daftar_alat = [];

while ($alat = mysqli_fetch_assoc($data_alat)) {
    $subtotal = (float) $alat['tarif_sewa']
              * (int) $alat['jumlah']
              * $lama_sewa;

    $total_bayar += $subtotal;

    $daftar_alat[] = [
        'nama' => $alat['nama_alat'],
        'jumlah' => (int) $alat['jumlah'],
        'tarif' => (float) $alat['tarif_sewa'],
        'subtotal' => $subtotal
    ];
}

// Periksa pembayaran yang sudah ada
$cek_bayar = mysqli_query($koneksi, "
    SELECT *
    FROM pembayaran
    WHERE id_peminjaman = $id_peminjaman
    LIMIT 1
");

if (!$cek_bayar) {
    die("Gagal memeriksa pembayaran: " . mysqli_error($koneksi));
}

$pembayaran = mysqli_fetch_assoc($cek_bayar);

// Proses konfirmasi pembayaran
if (isset($_POST['konfirmasi_bayar'])) {
    if ($pembayaran && $pembayaran['status'] == 'lunas') {
        $pesan = "Pembayaran sudah lunas.";
    } elseif ($pembayaran && $pembayaran['status'] == 'menunggu_konfirmasi') {
        $pesan = "Pembayaran masih menunggu konfirmasi Petugas.";
    } else {
        if ($pembayaran) {
            // Ajukan ulang pembayaran yang ditolak
            $simpan = mysqli_query($koneksi, "
                UPDATE pembayaran
                SET total_bayar = $total_bayar,
                    metode = '$metode',
                    status = 'menunggu_konfirmasi',
                    tanggal_bayar = NOW()
                WHERE id_peminjaman = $id_peminjaman
                  AND status = 'ditolak'
            ");
        } else {
            // Simpan pembayaran pertama kali
            $simpan = mysqli_query($koneksi, "
                INSERT INTO pembayaran
                    (id_peminjaman, total_bayar, metode, status, tanggal_bayar)
                VALUES
                    ($id_peminjaman, $total_bayar, '$metode',
                     'menunggu_konfirmasi', NOW())
            ");
        }

        if (!$simpan) {
            die("Gagal menyimpan pembayaran: " . mysqli_error($koneksi));
        }

        header("Location: pembayaran.php?id=" . $id_peminjaman . "&berhasil=1");
        exit;
    }
}

if (isset($_GET['berhasil'])) {
    $pesan = "Konfirmasi pembayaran berhasil dikirim. Silakan tunggu Petugas memverifikasi pembayaran.";
}

// Ambil ulang status pembayaran terbaru
$cek_bayar = mysqli_query($koneksi, "
    SELECT *
    FROM pembayaran
    WHERE id_peminjaman = $id_peminjaman
    LIMIT 1
");

if (!$cek_bayar) {
    die("Gagal mengambil status pembayaran: " . mysqli_error($koneksi));
}

$pembayaran = mysqli_fetch_assoc($cek_bayar);
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pembayaran - KEBUN PINJAM</title>
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
}
.qris {
    width: 100%;
    max-width: 300px;
    height: auto;
    background: white;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 10px;
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
        <a href="data_pengembalian.php">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <div class="col-md-9 col-lg-10 konten">
        <h3 class="judul">Pembayaran Peminjaman</h3>
        <p class="text-secondary mb-4">
            ID Peminjaman: <?= $id_peminjaman; ?>
        </p>

        <?php if ($pesan != "") { ?>
            <div class="alert alert-info">
                <?= htmlspecialchars($pesan); ?>
            </div>
        <?php } ?>

        <div class="kartu">
            <h5 class="judul mb-3">Rincian Pembayaran</h5>

            <p>
                <strong>Tanggal Pinjam:</strong>
                <?= htmlspecialchars($pinjam['tanggal_pinjam']); ?>
            </p>

            <p>
                <strong>Rencana Kembali:</strong>
                <?= htmlspecialchars($pinjam['tanggal_rencana_kembali']); ?>
            </p>

            <p>
                <strong>Lama Sewa:</strong>
                <?= $lama_sewa; ?> hari
            </p>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Alat</th>
                        <th>Jumlah</th>
                        <th>Tarif per Hari</th>
                        <th>Subtotal</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (count($daftar_alat) > 0) { ?>
                        <?php foreach ($daftar_alat as $i => $alat) { ?>
                        <tr>
                            <td><?= $i + 1; ?></td>
                            <td><?= htmlspecialchars($alat['nama']); ?></td>
                            <td><?= $alat['jumlah']; ?></td>
                            <td>Rp<?= number_format($alat['tarif'], 0, ',', '.'); ?></td>
                            <td>Rp<?= number_format($alat['subtotal'], 0, ',', '.'); ?></td>
                        </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="5" class="text-center">
                                Tidak ada detail alat.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th colspan="4" class="text-end">Total Pembayaran</th>
                        <th>Rp<?= number_format($total_bayar, 0, ',', '.'); ?></th>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <p>
                <strong>Metode Pembayaran:</strong>
                <?= strtoupper(htmlspecialchars($metode)); ?>
            </p>

            <?php if ($metode == 'dana') { ?>
                <div class="alert alert-secondary text-center">
                    <h5 class="judul">Pembayaran DANA / QRIS</h5>

                    <p>
                        Silakan scan kode QRIS berikut menggunakan aplikasi
                        DANA, lalu selesaikan pembayaran sesuai total tagihan.
                    </p>

                    <img
                        src="../assets/img/qris.png"
                        alt="Kode QRIS KEBUN PINJAM"
                        class="qris"
                    >

                    <p class="mt-3 mb-1">
                        <strong>Total Pembayaran</strong>
                    </p>

                    <h4 class="judul">
                        Rp<?= number_format($total_bayar, 0, ',', '.'); ?>
                    </h4>

                    <small>
                        Setelah membayar, tekan tombol konfirmasi di bawah.
                    </small>
                </div>
            <?php } else { ?>
                <div class="alert alert-secondary">
                    Untuk metode Cash, silakan lakukan pembayaran tunai
                    kepada Petugas. Setelah membayar, tekan tombol
                    konfirmasi di bawah.
                </div>
            <?php } ?>

            <?php if ($pembayaran && $pembayaran['status'] == 'lunas') { ?>

                <div class="alert alert-success">
                    Pembayaran sudah lunas.
                </div>

            <?php } elseif ($pembayaran && $pembayaran['status'] == 'menunggu_konfirmasi') { ?>

                <div class="alert alert-warning">
                    Pembayaran sedang menunggu konfirmasi Petugas.
                </div>

            <?php } else { ?>

                <form method="POST"
                      onsubmit="return confirm('Pastikan kamu sudah melakukan pembayaran. Kirim konfirmasi ke Petugas?');">

                    <button type="submit"
                            name="konfirmasi_bayar"
                            class="btn btn-success">

                        <?= ($pembayaran && $pembayaran['status'] == 'ditolak')
                            ? 'Ajukan Pembayaran Ulang'
                            : 'Saya Sudah Membayar'; ?>

                    </button>
                </form>

            <?php } ?>

            <a href="data_peminjaman.php" class="btn btn-secondary mt-3">
                Kembali ke Data Peminjaman
            </a>
        </div>
    </div>

</div>
</div>
</body>
</html>