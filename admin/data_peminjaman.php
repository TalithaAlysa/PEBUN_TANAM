
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";

// Tambah peminjaman manual oleh Admin
if (isset($_POST['tambah_peminjaman'])) {
    $id_user = (int) ($_POST['id_user'] ?? 0);
    $id_alat = (int) ($_POST['id_alat'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_kembali = $_POST['tanggal_rencana_kembali'] ?? '';

    if ($id_user <= 0 || $id_alat <= 0 || $jumlah <= 0 ||
        $tanggal_pinjam == '' || $tanggal_kembali == '' ||
        $tanggal_kembali < $tanggal_pinjam) {
        $pesan = "Data peminjaman belum benar. Periksa kembali semua isian dan tanggal!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            // Pastikan pengguna memang peminjam
            $cek_user = mysqli_query($koneksi,
                "SELECT id_user FROM users WHERE id_user=$id_user AND level='peminjam' LIMIT 1"
            );
            if (!$cek_user || mysqli_num_rows($cek_user) == 0) {
                throw new Exception("Pengguna yang dipilih bukan akun peminjam!");
            }

            // Pastikan alat ada dan jumlahnya tidak melebihi stok tersedia
            $cek_alat = mysqli_query($koneksi,
                "SELECT id_alat, stok FROM alat WHERE id_alat=$id_alat FOR UPDATE"
            );
            if (!$cek_alat || mysqli_num_rows($cek_alat) == 0) {
                throw new Exception("Alat tidak ditemukan!");
            }
            $alat_dipilih = mysqli_fetch_assoc($cek_alat);
            if ($jumlah > (int) $alat_dipilih['stok']) {
                throw new Exception("Jumlah alat melebihi stok yang tersedia!");
            }

            $tanggal_pinjam_sql = mysqli_real_escape_string($koneksi, $tanggal_pinjam);
            $tanggal_kembali_sql = mysqli_real_escape_string($koneksi, $tanggal_kembali);

            $simpan = mysqli_query($koneksi,
                "INSERT INTO peminjaman (id_user, tanggal_pinjam, tanggal_rencana_kembali, status)
                 VALUES ($id_user, '$tanggal_pinjam_sql', '$tanggal_kembali_sql', 'menunggu')"
            );
            if (!$simpan) {
                throw new Exception("Gagal menambahkan data peminjaman!");
            }

            $id_baru = mysqli_insert_id($koneksi);
            if (!mysqli_query($koneksi,
                "INSERT INTO detail_peminjaman (id_peminjaman, id_alat, jumlah)
                 VALUES ($id_baru, $id_alat, $jumlah)"
            )) {
                throw new Exception("Gagal menyimpan detail alat yang dipinjam!");
            }

            mysqli_commit($koneksi);
            header("Location: data_peminjaman.php?pesan=tambah");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// Ubah status peminjaman
if (isset($_POST['ubah_status'])) {
    $id = (int) $_POST['id_peminjaman'];
    $status = $_POST['status'];

    $status_valid = ['menunggu', 'disetujui', 'ditolak', 'dikembalikan'];

    if (in_array($status, $status_valid)) {
        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_query($koneksi,
                "SELECT status FROM peminjaman
                 WHERE id_peminjaman=$id FOR UPDATE"
            );

            if (!$cek || mysqli_num_rows($cek) == 0) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $data = mysqli_fetch_assoc($cek);
            $status_lama = $data['status'];

            if ($status_lama != $status) {

                // Stok dikurangi saat peminjaman disetujui
                if ($status_lama == 'menunggu' && $status == 'disetujui') {
                    $detail = mysqli_query($koneksi,
                        "SELECT id_alat, jumlah
                         FROM detail_peminjaman
                         WHERE id_peminjaman=$id"
                    );

                    if (!$detail || mysqli_num_rows($detail) == 0) {
                        throw new Exception("Detail alat yang dipinjam tidak ditemukan!");
                    }

                    while ($d = mysqli_fetch_assoc($detail)) {
                        $id_alat = (int) $d['id_alat'];
                        $jumlah = (int) $d['jumlah'];

                        $update_stok = mysqli_query($koneksi,
                            "UPDATE alat
                             SET stok=stok-$jumlah
                             WHERE id_alat=$id_alat AND stok >= $jumlah"
                        );

                        if (!$update_stok || mysqli_affected_rows($koneksi) == 0) {
                            throw new Exception("Stok alat tidak mencukupi!");
                        }
                    }
                }

                // Stok dikembalikan saat peminjaman yang disetujui ditolak
                if ($status_lama == 'disetujui' && $status == 'ditolak') {
                    $detail = mysqli_query($koneksi,
                        "SELECT id_alat, jumlah
                         FROM detail_peminjaman
                         WHERE id_peminjaman=$id"
                    );

                    while ($d = mysqli_fetch_assoc($detail)) {
                        $id_alat = (int) $d['id_alat'];
                        $jumlah = (int) $d['jumlah'];

                        if (!mysqli_query($koneksi,
                            "UPDATE alat SET stok=stok+$jumlah
                             WHERE id_alat=$id_alat"
                        )) {
                            throw new Exception("Gagal mengembalikan stok alat!");
                        }
                    }
                }

                $status_sql = mysqli_real_escape_string($koneksi, $status);

                if (!mysqli_query($koneksi,
                    "UPDATE peminjaman
                     SET status='$status_sql'
                     WHERE id_peminjaman=$id"
                )) {
                    throw new Exception("Gagal mengubah status peminjaman!");
                }
            }

            mysqli_commit($koneksi);
            header("Location: data_peminjaman.php?pesan=berhasil");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    } else {
        $pesan = "Status peminjaman tidak valid!";
    }
}

// Hapus peminjaman
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    $cek = mysqli_query($koneksi,
        "SELECT id_pengembalian
         FROM pengembalian
         WHERE id_peminjaman=$id
         LIMIT 1"
    );

    if (!$cek) {
        $pesan = "Gagal memeriksa data pengembalian!";
    } elseif (mysqli_num_rows($cek) > 0) {
        $pesan = "Data tidak bisa dihapus karena sudah memiliki data pengembalian!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek_pinjam = mysqli_query($koneksi,
                "SELECT status FROM peminjaman
                 WHERE id_peminjaman=$id FOR UPDATE"
            );

            if (!$cek_pinjam || mysqli_num_rows($cek_pinjam) == 0) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $pinjam = mysqli_fetch_assoc($cek_pinjam);

            // Kembalikan stok jika peminjaman masih disetujui
            if ($pinjam['status'] == 'disetujui') {
                $detail = mysqli_query($koneksi,
                    "SELECT id_alat, jumlah
                     FROM detail_peminjaman
                     WHERE id_peminjaman=$id"
                );

                while ($d = mysqli_fetch_assoc($detail)) {
                    $id_alat = (int) $d['id_alat'];
                    $jumlah = (int) $d['jumlah'];

                    if (!mysqli_query($koneksi,
                        "UPDATE alat SET stok=stok+$jumlah
                         WHERE id_alat=$id_alat"
                    )) {
                        throw new Exception("Gagal mengembalikan stok alat!");
                    }
                }
            }

            if (!mysqli_query($koneksi,
                "DELETE FROM detail_peminjaman
                 WHERE id_peminjaman=$id"
            )) {
                throw new Exception("Gagal menghapus detail peminjaman!");
            }

            if (!mysqli_query($koneksi,
                "DELETE FROM peminjaman
                 WHERE id_peminjaman=$id"
            )) {
                throw new Exception("Gagal menghapus peminjaman!");
            }

            mysqli_commit($koneksi);
            header("Location: data_peminjaman.php?pesan=hapus");
            exit;

        } catch (Exception $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// Pesan berhasil
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'berhasil') {
        $pesan = "Status peminjaman berhasil diubah!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Data peminjaman berhasil dihapus!";
    } elseif ($_GET['pesan'] == 'tambah') {
        $pesan = "Data peminjaman berhasil ditambahkan!";
    }
}

// Ambil data peminjaman beserta daftar alat
$data = mysqli_query($koneksi,
    "SELECT
        peminjaman.*,
        users.nama,
        GROUP_CONCAT(
            CONCAT(alat.nama_alat, ' (', detail_peminjaman.jumlah, ')')
            SEPARATOR ', '
        ) AS daftar_alat
     FROM peminjaman
     JOIN users ON peminjaman.id_user=users.id_user
     LEFT JOIN detail_peminjaman
        ON peminjaman.id_peminjaman=detail_peminjaman.id_peminjaman
     LEFT JOIN alat
        ON detail_peminjaman.id_alat=alat.id_alat
     GROUP BY peminjaman.id_peminjaman
     ORDER BY peminjaman.id_peminjaman DESC"
);

// Data untuk form tambah peminjaman manual
$daftar_peminjam = mysqli_query($koneksi,
    "SELECT id_user, nama FROM users WHERE level='peminjam' ORDER BY nama ASC"
);
$daftar_alat = mysqli_query($koneksi,
    "SELECT id_alat, nama_alat, stok FROM alat WHERE stok > 0 ORDER BY nama_alat ASC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Penyewaan - KEBUN PINJAM</title>

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

        .btn-hapus {
            background: #C96B6B;
            color: white;
            border: none;
        }

        .btn-hapus:hover {
            background: #B85A5A;
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
            <a href="data_peminjaman.php" class="menu-aktif">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Data Penyewaan</h3>

            <p class="text-secondary mb-4">
                Selamat datang,
                <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>!
            </p>

            <!-- PESAN -->
            <?php if ($pesan != "") { ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($pesan); ?>
                </div>
            <?php } ?>

            <!-- FORM TAMBAH PEMINJAMAN MANUAL -->
            <div class="kartu mb-4">
                <h5 class="judul mb-3">Tambah Peminjaman Manual</h5>
                <form method="POST" class="row g-3" onsubmit="return confirm('Yakin menambahkan peminjaman ini?')">
                    <div class="col-md-6">
                        <label class="form-label">Nama Peminjam</label>
                        <select name="id_user" class="form-select" required>
                            <option value="">-- Pilih peminjam --</option>
                            <?php if ($daftar_peminjam) { while ($u = mysqli_fetch_assoc($daftar_peminjam)) { ?>
                                <option value="<?= (int) $u['id_user']; ?>"><?= htmlspecialchars($u['nama']); ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alat</label>
                        <select name="id_alat" class="form-select" required>
                            <option value="">-- Pilih alat --</option>
                            <?php if ($daftar_alat) { while ($a = mysqli_fetch_assoc($daftar_alat)) { ?>
                                <option value="<?= (int) $a['id_alat']; ?>"><?= htmlspecialchars($a['nama_alat']); ?> (stok: <?= (int) $a['stok']; ?>)</option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="jumlah" class="form-control" min="1" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Pinjam</label>
                        <input type="date" name="tanggal_pinjam" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Rencana Kembali</label>
                        <input type="date" name="tanggal_rencana_kembali" class="form-control" min="<?= date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="tambah_peminjaman" class="btn btn-hijau">Tambah Peminjaman</button>
                    </div>
                </form>
                <small class="text-secondary d-block mt-2">Peminjaman manual akan berstatus Menunggu. Stok baru dikurangi saat peminjaman disetujui.</small>
            </div>

            <!-- TABEL PEMINJAMAN -->
            <div class="kartu mt-3">

                <h5 class="judul mb-3">Daftar Penyewaan</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">

                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Peminjam</th>
                                <th>Tanggal Pinjam</th>
                                <th>Rencana Kembali</th>
                                <th>Alat yang Dipinjam</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $no = 1;

                            if ($data && mysqli_num_rows($data) > 0) {
                                while ($row = mysqli_fetch_assoc($data)) {
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td>
                                        <?= htmlspecialchars($row['nama']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['tanggal_pinjam']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['tanggal_rencana_kembali']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($row['daftar_alat'] ?? '-'); ?>
                                    </td>

                                    <td>
                                        <?php
                                        $warna = 'secondary';

                                        if ($row['status'] == 'menunggu') {
                                            $warna = 'warning';
                                        } elseif ($row['status'] == 'disetujui') {
                                            $warna = 'success';
                                        } elseif ($row['status'] == 'ditolak') {
                                            $warna = 'danger';
                                        } elseif ($row['status'] == 'dikembalikan') {
                                            $warna = 'primary';
                                        }
                                        ?>

                                        <span class="badge text-bg-<?= $warna; ?>">
                                            <?= ucfirst(htmlspecialchars($row['status'])); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <form method="POST" class="d-flex gap-2 align-items-center mb-2">

                                            <input type="hidden" name="id_peminjaman"
                                                   value="<?= $row['id_peminjaman']; ?>">

                                            <select name="status" class="form-select form-select-sm">
                                                <?php
                                                $status_list = [
                                                    'menunggu',
                                                    'disetujui',
                                                    'ditolak',
                                                    'dikembalikan'
                                                ];

                                                foreach ($status_list as $s) {
                                                ?>
                                                    <option value="<?= $s; ?>"
                                                        <?= $row['status'] == $s ? 'selected' : ''; ?>>
                                                        <?= ucfirst($s); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>

                                            <button type="submit" name="ubah_status"
                                                    class="btn btn-sm btn-hijau">
                                                Simpan
                                            </button>

                                        </form>

                                        <a class="btn btn-sm btn-hapus"
                                           href="data_peminjaman.php?hapus=<?= $row['id_peminjaman']; ?>"
                                           onclick="return confirm('Yakin ingin menghapus peminjaman ini?')">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        Belum ada data penyewaan.
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
