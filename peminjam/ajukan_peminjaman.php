
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'peminjam') {
    header("Location: ../index.php");
    exit;
}

$id_user = (int) $_SESSION['id_user'];
$pesan_error = "";
$tanggal_rencana = $_POST['tanggal_rencana_kembali'] ?? "";
$metode = $_POST['metode_pembayaran'] ?? "";

// Ambil daftar alat yang tersedia
$data_alat = mysqli_query($koneksi, "
    SELECT id_alat, nama_alat, tarif_sewa, stok, kondisi
    FROM alat
    WHERE stok > 0
    ORDER BY nama_alat ASC
");

if (!$data_alat) {
    die("Gagal mengambil data alat: " . mysqli_error($koneksi));
}

$daftar_alat = [];

while ($alat = mysqli_fetch_assoc($data_alat)) {
    $daftar_alat[] = $alat;
}

// Proses pengajuan peminjaman
if (isset($_POST['ajukan'])) {
    $jumlah_input = $_POST['jumlah'] ?? [];
    $pilihan = [];

    if (is_array($jumlah_input)) {
        foreach ($jumlah_input as $id_alat => $jumlah) {
            $id_alat = (int) $id_alat;
            $jumlah = filter_var($jumlah, FILTER_VALIDATE_INT);

            if ($id_alat > 0 && $jumlah !== false && $jumlah > 0) {
                $pilihan[$id_alat] = $jumlah;
            }
        }
    }

    $tanggal_rencana = $_POST['tanggal_rencana_kembali'] ?? "";
    $metode = $_POST['metode_pembayaran'] ?? "";
    $hari_ini = date('Y-m-d');

    if (count($pilihan) == 0) {
        $pesan_error = "Pilih minimal satu alat dan masukkan jumlahnya.";
    } elseif (
        !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_rencana) ||
        !checkdate(
            (int) substr($tanggal_rencana, 5, 2),
            (int) substr($tanggal_rencana, 8, 2),
            (int) substr($tanggal_rencana, 0, 4)
        ) ||
        $tanggal_rencana < $hari_ini
    ) {
        $pesan_error = "Tanggal kembali harus hari ini atau setelahnya.";
    } elseif (!in_array($metode, ['cash', 'dana'], true)) {
        $pesan_error = "Pilih metode pembayaran Cash atau DANA.";
    } else {
        try {
            mysqli_begin_transaction($koneksi);

            // Periksa stok alat
            $id_pilihan = array_keys($pilihan);
            $id_sql = implode(',', array_map('intval', $id_pilihan));

            $cek_stok = mysqli_query($koneksi, "
                SELECT id_alat, nama_alat, tarif_sewa, stok
                FROM alat
                WHERE id_alat IN ($id_sql)
                FOR UPDATE
            ");

            if (!$cek_stok) {
                throw new Exception(mysqli_error($koneksi));
            }

            $alat_tersedia = [];

            while ($alat = mysqli_fetch_assoc($cek_stok)) {
                $alat_tersedia[(int) $alat['id_alat']] = $alat;
            }

            foreach ($pilihan as $id_alat => $jumlah) {
                if (!isset($alat_tersedia[$id_alat])) {
                    throw new Exception(
                        "Ada alat yang tidak ditemukan. Silakan muat ulang halaman."
                    );
                }

                if ($jumlah > (int) $alat_tersedia[$id_alat]['stok']) {
                    throw new Exception(
                        "Jumlah " . $alat_tersedia[$id_alat]['nama_alat'] .
                        " melebihi stok yang tersedia."
                    );
                }
            }

            // Simpan peminjaman
            $tanggal_pinjam = date('Y-m-d');

            $stmt = mysqli_prepare($koneksi, "
                INSERT INTO peminjaman
                    (
                        id_user,
                        tanggal_pinjam,
                        tanggal_rencana_kembali,
                        status,
                        metode_pembayaran
                    )
                VALUES (?, ?, ?, 'menunggu', ?)
            ");

            if (!$stmt) {
                throw new Exception(mysqli_error($koneksi));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "isss",
                $id_user,
                $tanggal_pinjam,
                $tanggal_rencana,
                $metode
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception(mysqli_stmt_error($stmt));
            }

            $id_peminjaman = mysqli_insert_id($koneksi);
            mysqli_stmt_close($stmt);

            // Simpan detail peminjaman
            $stmt_detail = mysqli_prepare($koneksi, "
                INSERT INTO detail_peminjaman
                    (id_peminjaman, id_alat, jumlah)
                VALUES (?, ?, ?)
            ");

            if (!$stmt_detail) {
                throw new Exception(mysqli_error($koneksi));
            }

            foreach ($pilihan as $id_alat => $jumlah) {
                mysqli_stmt_bind_param(
                    $stmt_detail,
                    "iii",
                    $id_peminjaman,
                    $id_alat,
                    $jumlah
                );

                if (!mysqli_stmt_execute($stmt_detail)) {
                    throw new Exception(mysqli_stmt_error($stmt_detail));
                }
            }

            mysqli_stmt_close($stmt_detail);

            // Catat aktivitas
            $aktivitas = "Mengajukan peminjaman ID " . $id_peminjaman .
                " dengan " . count($pilihan) . " jenis alat";

            $stmt_log = mysqli_prepare($koneksi, "
                INSERT INTO log_aktivitas (id_user, aktivitas)
                VALUES (?, ?)
            ");

            if (!$stmt_log) {
                throw new Exception(mysqli_error($koneksi));
            }

            mysqli_stmt_bind_param(
                $stmt_log,
                "is",
                $id_user,
                $aktivitas
            );

            if (!mysqli_stmt_execute($stmt_log)) {
                throw new Exception(mysqli_stmt_error($stmt_log));
            }

            mysqli_stmt_close($stmt_log);

            mysqli_commit($koneksi);

            header("Location: data_peminjaman.php?pesan=berhasil");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan_error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ajukan Peminjaman - KEBUN PINJAM</title>

<link rel="stylesheet" href="../assets/css/bootstrap.min.css">

<style>
body {
    background: #E8EDE6;
    margin: 0;
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

.sidebar a:hover,
.sidebar a.menu-aktif {
    background: #557355;
}

.sidebar a.menu-aktif {
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
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 3px 10px #00000012;
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

.form-label {
    font-weight: 500;
    margin-bottom: 8px;
}

.info-sewa {
    background: #F5F7F3;
    border: 1px solid #E0E6DC;
    border-radius: 8px;
    padding: 15px;
}

@media (max-width: 767px) {
    .sidebar {
        min-height: auto;
    }

    .konten {
        padding: 20px 15px;
    }

    .kartu {
        padding: 16px;
    }
}
</style>
</head>

<body>

<div class="container-fluid">
<div class="row">

    <!-- Sidebar -->
    <div class="col-md-3 col-lg-2 sidebar">
        <h4>KEBUN PINJAM</h4>

        <a href="dashboard.php">Dashboard</a>
        <a href="data_alat.php">Data Alat</a>
        <a href="ajukan_peminjaman.php" class="menu-aktif">
            Ajukan Peminjaman
        </a>
        <a href="data_peminjaman.php">Data Peminjaman</a>
        <a href="data_pengembalian.php">Pengembalian</a>
        <a href="../logout.php">Logout</a>
    </div>

    <!-- Konten -->
    <div class="col-md-9 col-lg-10 konten">

        <h3 class="judul mb-2">Ajukan Peminjaman Alat</h3>
        <p class="text-secondary mb-4">
            Pilih alat berkebun yang ingin dipinjam, lalu lengkapi data peminjaman.
        </p>

        <?php if ($pesan_error != "") { ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($pesan_error); ?>
            </div>
        <?php } ?>

        <form method="POST" id="formPeminjaman">

            <!-- Pilih alat -->
            <div class="kartu">
                <h5 class="judul mb-3">Pilih Alat</h5>

                <?php if (count($daftar_alat) > 0) { ?>
                    <div class="row g-3 align-items-end">

                        <div class="col-md-7">
                            <label class="form-label">Nama Alat</label>

                            <select id="pilihAlat" class="form-select">
                                <option value="">-- Pilih alat --</option>

                                <?php foreach ($daftar_alat as $alat) { ?>
                                    <option
                                        value="<?= (int) $alat['id_alat']; ?>"
                                        data-nama="<?= htmlspecialchars($alat['nama_alat'], ENT_QUOTES); ?>"
                                        data-tarif="<?= (float) $alat['tarif_sewa']; ?>"
                                        data-stok="<?= (int) $alat['stok']; ?>">

                                        <?= htmlspecialchars($alat['nama_alat']); ?>
                                        - Rp<?= number_format((float) $alat['tarif_sewa'], 0, ',', '.'); ?>/hari
                                        (Stok: <?= (int) $alat['stok']; ?>)
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Jumlah</label>
                            <input
                                type="number"
                                id="jumlahAlat"
                                class="form-control"
                                value="1"
                                min="1"
                            >
                        </div>

                        <div class="col-md-2">
                            <button
                                type="button"
                                id="tambahAlat"
                                class="btn btn-hijau w-100">
                                + Tambah
                            </button>
                        </div>

                    </div>
                <?php } else { ?>
                    <div class="alert alert-secondary mb-0">
                        Belum ada alat yang tersedia untuk dipinjam.
                    </div>
                <?php } ?>
            </div>

            <!-- Keranjang -->
            <div class="kartu">
                <h5 class="judul mb-3">Keranjang Peminjaman</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Alat</th>
                                <th>Tarif per Hari</th>
                                <th>Jumlah</th>
                                <th>Subtotal per Hari</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody id="isiKeranjang">
                            <tr>
                                <td colspan="6" class="text-center">
                                    Keranjang masih kosong.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tanggal dan pembayaran -->
                <h5 class="judul mt-4 mb-3">Informasi Peminjaman</h5>

                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Tanggal Peminjaman</label>
                        <input
                            type="date"
                            class="form-control"
                            value="<?= date('Y-m-d'); ?>"
                            readonly
                        >
                        <small class="text-muted">
                            Diisi otomatis saat pengajuan.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tanggal Rencana Kembali</label>
                        <input
                            type="date"
                            name="tanggal_rencana_kembali"
                            id="tanggalKembali"
                            class="form-control"
                            min="<?= date('Y-m-d'); ?>"
                            value="<?= htmlspecialchars($tanggal_rencana); ?>"
                            required
                        >
                        <small class="text-muted">
                            Pilih tanggal rencana pengembalian alat.
                        </small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Metode Pembayaran</label>
                        <select
                            name="metode_pembayaran"
                            class="form-select"
                            required
                        >
                            <option value="">-- Pilih metode --</option>

                            <option value="cash"
                                <?= $metode == 'cash' ? 'selected' : ''; ?>>
                                Cash
                            </option>

                            <option value="dana"
                                <?= $metode == 'dana' ? 'selected' : ''; ?>>
                                DANA
                            </option>
                        </select>

                        <small class="text-muted">
                            Pembayaran setelah peminjaman disetujui petugas.
                        </small>
                    </div>

                </div>

                <!-- Estimasi -->
                <div class="info-sewa mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span>Lama Peminjaman</span>
                        <strong id="lamaPinjam">1 hari</strong>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between align-items-center">
                        <span>Total Estimasi Sewa</span>
                        <strong id="totalSewa" class="judul">Rp0</strong>
                    </div>
                </div>

                <p class="text-secondary small mt-3">
                    Estimasi dihitung berdasarkan tarif per hari, jumlah alat,
                    dan lama peminjaman. Total akhir mengikuti ketentuan
                    penyewaan yang berlaku.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button
                        type="submit"
                        name="ajukan"
                        class="btn btn-hijau"
                        id="tombolAjukan"
                        disabled>
                        Ajukan Peminjaman
                    </button>

                    <a href="data_peminjaman.php" class="btn btn-secondary">
                        Kembali
                    </a>
                </div>

            </div>
        </form>
    </div>

</div>
</div>

<script>
const pilihAlat = document.getElementById('pilihAlat');
const jumlahAlat = document.getElementById('jumlahAlat');
const tambahAlat = document.getElementById('tambahAlat');
const isiKeranjang = document.getElementById('isiKeranjang');
const tanggalKembali = document.getElementById('tanggalKembali');
const totalSewa = document.getElementById('totalSewa');
const lamaPinjam = document.getElementById('lamaPinjam');
const tombolAjukan = document.getElementById('tombolAjukan');

const keranjang = {};

function rupiah(angka) {
    return 'Rp' + Number(angka).toLocaleString('id-ID');
}

function hitungHari() {
    const sekarang = new Date();
    sekarang.setHours(0, 0, 0, 0);

    const tanggal = tanggalKembali.value
        ? new Date(tanggalKembali.value + 'T00:00:00')
        : new Date(sekarang);

    const selisih = Math.round((tanggal - sekarang) / 86400000);

    return Math.max(1, selisih + 1);
}

function tampilkanKeranjang() {
    isiKeranjang.innerHTML = '';

    const ids = Object.keys(keranjang);
    const hari = hitungHari();
    let totalPerHari = 0;

    lamaPinjam.textContent = hari + ' hari';

    if (ids.length === 0) {
        isiKeranjang.innerHTML = `
            <tr>
                <td colspan="6" class="text-center">
                    Keranjang masih kosong.
                </td>
            </tr>
        `;

        totalSewa.textContent = rupiah(0);
        tombolAjukan.disabled = true;
        return;
    }

    ids.forEach((id, index) => {
        const alat = keranjang[id];
        const subtotal = alat.tarif * alat.jumlah;

        totalPerHari += subtotal;

        const baris = document.createElement('tr');

        const kolomNo = document.createElement('td');
        kolomNo.textContent = index + 1;

        const kolomNama = document.createElement('td');
        kolomNama.textContent = alat.nama;

        const kolomTarif = document.createElement('td');
        kolomTarif.textContent = rupiah(alat.tarif);

        const kolomJumlah = document.createElement('td');
        const inputJumlah = document.createElement('input');

        inputJumlah.type = 'number';
        inputJumlah.min = '1';
        inputJumlah.max = alat.stok;
        inputJumlah.value = alat.jumlah;
        inputJumlah.className = 'form-control';
        inputJumlah.style.width = '90px';
        inputJumlah.name = 'jumlah[' + id + ']';

        inputJumlah.addEventListener('change', function() {
            let jumlah = parseInt(this.value, 10);

            if (isNaN(jumlah) || jumlah < 1) {
                jumlah = 1;
            }

            if (jumlah > alat.stok) {
                jumlah = alat.stok;
                alert('Stok maksimal ' + alat.stok + ' buah.');
            }

            alat.jumlah = jumlah;
            tampilkanKeranjang();
        });

        kolomJumlah.appendChild(inputJumlah);

        const kolomSubtotal = document.createElement('td');
        kolomSubtotal.textContent = rupiah(subtotal);

        const kolomAksi = document.createElement('td');
        const tombolHapus = document.createElement('button');

        tombolHapus.type = 'button';
        tombolHapus.className = 'btn btn-danger btn-sm';
        tombolHapus.textContent = 'Hapus';

        tombolHapus.addEventListener('click', function() {
            delete keranjang[id];
            tampilkanKeranjang();
        });

        kolomAksi.appendChild(tombolHapus);

        baris.append(
            kolomNo,
            kolomNama,
            kolomTarif,
            kolomJumlah,
            kolomSubtotal,
            kolomAksi
        );

        isiKeranjang.appendChild(baris);
    });

    totalSewa.textContent = rupiah(totalPerHari * hari);
    tombolAjukan.disabled = false;
}

if (tambahAlat) {
    tambahAlat.addEventListener('click', function() {
        const opsi = pilihAlat.options[pilihAlat.selectedIndex];

        if (!pilihAlat.value) {
            alert('Pilih alat terlebih dahulu.');
            return;
        }

        const id = pilihAlat.value;
        const jumlah = parseInt(jumlahAlat.value, 10);
        const stok = Number(opsi.dataset.stok);

        if (isNaN(jumlah) || jumlah < 1 || jumlah > stok) {
            alert('Jumlah alat tidak sesuai stok.');
            return;
        }

        if (keranjang[id]) {
            if (keranjang[id].jumlah + jumlah > stok) {
                alert('Jumlah melebihi stok tersedia.');
                return;
            }

            keranjang[id].jumlah += jumlah;
        } else {
            keranjang[id] = {
                nama: opsi.dataset.nama,
                tarif: Number(opsi.dataset.tarif),
                stok: stok,
                jumlah: jumlah
            };
        }

        pilihAlat.value = '';
        jumlahAlat.value = 1;

        tampilkanKeranjang();
    });
}

tanggalKembali.addEventListener('change', tampilkanKeranjang);

document.getElementById('formPeminjaman').addEventListener('submit', function(e) {
    if (Object.keys(keranjang).length === 0) {
        e.preventDefault();
        alert('Masukkan minimal satu alat ke keranjang.');
        return;
    }

    if (!confirm('Yakin ingin mengajukan semua alat dalam satu transaksi?')) {
        e.preventDefault();
    }
});

tampilkanKeranjang();
</script>

</body>
</html>
