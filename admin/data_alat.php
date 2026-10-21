
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$id_alat = "";
$nama_alat = "";
$id_kategori = "";
$tarif_sewa = "";
$stok = "";
$kondisi = "Baik";
$cari = trim($_GET['cari'] ?? "");

// Mencatat aktivitas admin
function catatAktivitasAdmin($koneksi, $aktivitas) {
    $id_user = (int) ($_SESSION['id_user'] ?? 0);
    $aktivitas = mysqli_real_escape_string($koneksi, $aktivitas);

    $query = mysqli_query($koneksi, "
        INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
        VALUES ($id_user, '$aktivitas', NOW())
    ");

    if (!$query) {
        throw new Exception("Gagal mencatat aktivitas admin!");
    }
}

// PESAN BERHASIL
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'sukses') {
        $pesan = "Data alat berhasil disimpan!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Data alat berhasil dihapus!";
    }
}

// SIMPAN DAN EDIT ALAT
if (isset($_POST['simpan'])) {
    $id_alat = trim($_POST['id_alat'] ?? "");
    $nama_alat = trim($_POST['nama_alat'] ?? "");
    $id_kategori = (int) ($_POST['id_kategori'] ?? 0);
    $tarif_sewa = filter_var(
        $_POST['tarif_sewa'] ?? null,
        FILTER_VALIDATE_FLOAT
    );
    $stok = filter_var(
        $_POST['stok'] ?? null,
        FILTER_VALIDATE_INT
    );
    $kondisi = $_POST['kondisi'] ?? "";

    $daftar_kondisi = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    if (
        $nama_alat == "" ||
        $id_kategori <= 0 ||
        $tarif_sewa === false ||
        $tarif_sewa === null ||
        $tarif_sewa < 0 ||
        $stok === false ||
        $stok === null ||
        $stok < 0 ||
        !in_array($kondisi, $daftar_kondisi, true)
    ) {
        $pesan = "Data alat belum lengkap atau tidak valid!";
    } else {
        $cek_kategori = mysqli_query($koneksi, "
            SELECT id_kategori
            FROM kategori
            WHERE id_kategori = $id_kategori
        ");

        if (!$cek_kategori || mysqli_num_rows($cek_kategori) == 0) {
            $pesan = "Kategori tidak ditemukan!";
        } else {
            mysqli_begin_transaction($koneksi);

            try {
                $nama_sql = mysqli_real_escape_string(
                    $koneksi,
                    $nama_alat
                );

                $kondisi_sql = mysqli_real_escape_string(
                    $koneksi,
                    $kondisi
                );

                if ($id_alat == "") {
                    // TAMBAH ALAT
                    $query = mysqli_prepare($koneksi, "
                        INSERT INTO alat (
                            nama_alat,
                            id_kategori,
                            tarif_sewa,
                            stok,
                            kondisi
                        ) VALUES (?, ?, ?, ?, ?)
                    ");

                    if (!$query) {
                        throw new Exception("Gagal menyiapkan data alat!");
                    }

                    mysqli_stmt_bind_param(
                        $query,
                        "siids",
                        $nama_alat,
                        $id_kategori,
                        $tarif_sewa,
                        $stok,
                        $kondisi
                    );

                    if (!mysqli_stmt_execute($query)) {
                        throw new Exception("Gagal menambahkan alat!");
                    }

                    $id_baru = mysqli_insert_id($koneksi);

                    catatAktivitasAdmin(
                        $koneksi,
                        "Admin menambahkan alat: $nama_alat (ID $id_baru)"
                    );

                } else {
                    // EDIT ALAT
                    $id_alat = (int) $id_alat;

                    $cek_alat = mysqli_query($koneksi, "
                        SELECT id_alat
                        FROM alat
                        WHERE id_alat = $id_alat
                        FOR UPDATE
                    ");

                    if (!$cek_alat || mysqli_num_rows($cek_alat) == 0) {
                        throw new Exception("Data alat tidak ditemukan!");
                    }

                    $query = mysqli_prepare($koneksi, "
                        UPDATE alat
                        SET nama_alat = ?,
                            id_kategori = ?,
                            tarif_sewa = ?,
                            stok = ?,
                            kondisi = ?
                        WHERE id_alat = ?
                    ");

                    if (!$query) {
                        throw new Exception("Gagal menyiapkan perubahan alat!");
                    }

                    mysqli_stmt_bind_param(
                        $query,
                        "siidsi",
                        $nama_alat,
                        $id_kategori,
                        $tarif_sewa,
                        $stok,
                        $kondisi,
                        $id_alat
                    );

                    if (!mysqli_stmt_execute($query)) {
                        throw new Exception("Gagal mengubah data alat!");
                    }

                    catatAktivitasAdmin(
                        $koneksi,
                        "Admin mengubah data alat: $nama_alat (ID $id_alat)"
                    );
                }

                mysqli_commit($koneksi);

                header("Location: data_alat.php?pesan=sukses");
                exit;

            } catch (Throwable $e) {
                mysqli_rollback($koneksi);
                $pesan = $e->getMessage();
            }
        }
    }
}

// AMBIL DATA ALAT UNTUK DIEDIT
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $hasil = mysqli_query($koneksi, "
        SELECT *
        FROM alat
        WHERE id_alat = $id
    ");

    if ($hasil && mysqli_num_rows($hasil) > 0) {
        $data = mysqli_fetch_assoc($hasil);

        $id_alat = $data['id_alat'];
        $nama_alat = $data['nama_alat'];
        $id_kategori = $data['id_kategori'];
        $tarif_sewa = $data['tarif_sewa'];
        $stok = $data['stok'];
        $kondisi = $data['kondisi'];
    } else {
        $pesan = "Data alat tidak ditemukan!";
    }
}

// HAPUS ALAT
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];

    if ($id <= 0) {
        $pesan = "ID alat tidak valid!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek_alat = mysqli_query($koneksi, "
                SELECT nama_alat
                FROM alat
                WHERE id_alat = $id
                FOR UPDATE
            ");

            if (!$cek_alat || mysqli_num_rows($cek_alat) == 0) {
                throw new Exception("Data alat tidak ditemukan!");
            }

            $alat = mysqli_fetch_assoc($cek_alat);
            $nama_hapus = $alat['nama_alat'];

            // Cek apakah alat pernah tercatat dalam peminjaman
            $cek = mysqli_query($koneksi, "
                SELECT id_alat
                FROM detail_peminjaman
                WHERE id_alat = $id
                LIMIT 1
            ");

            if (!$cek) {
                throw new Exception("Gagal memeriksa data peminjaman!");
            }

            if (mysqli_num_rows($cek) > 0) {
                throw new Exception(
                    "Alat tidak bisa dihapus karena sudah tercatat dalam peminjaman!"
                );
            }

            $hapus = mysqli_query($koneksi, "
                DELETE FROM alat
                WHERE id_alat = $id
            ");

            if (!$hapus) {
                throw new Exception("Gagal menghapus alat!");
            }

            catatAktivitasAdmin(
                $koneksi,
                "Admin menghapus alat: $nama_hapus (ID $id)"
            );

            mysqli_commit($koneksi);

            header("Location: data_alat.php?pesan=hapus");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// DATA KATEGORI
$kategori = mysqli_query($koneksi, "
    SELECT *
    FROM kategori
    ORDER BY nama_kategori ASC
");

if (!$kategori) {
    die("Gagal mengambil data kategori: " . mysqli_error($koneksi));
}

// PENCARIAN ALAT
$stmt = mysqli_prepare($koneksi, "
    SELECT alat.*, kategori.nama_kategori
    FROM alat
    JOIN kategori
        ON alat.id_kategori = kategori.id_kategori
    WHERE alat.nama_alat LIKE ?
       OR kategori.nama_kategori LIKE ?
    ORDER BY alat.id_alat DESC
");

if (!$stmt) {
    die("Gagal menyiapkan pencarian: " . mysqli_error($koneksi));
}

$kata_cari = "%" . $cari . "%";

mysqli_stmt_bind_param($stmt, "ss", $kata_cari, $kata_cari);
mysqli_stmt_execute($stmt);

$data_alat = mysqli_stmt_get_result($stmt);

if (!$data_alat) {
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
            margin-bottom: 22px;
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

        .table {
            margin-bottom: 0;
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
            <a href="data_alat.php" class="menu-aktif">Data Alat</a>
            <a href="data_pengguna.php">Data Pengguna</a>
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Data Alat</h3>

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

            <!-- FORM TAMBAH DAN EDIT ALAT -->
            <div class="kartu">
                <h5 class="judul mb-3">
                    <?= $id_alat == "" ? "Tambah Alat" : "Edit Alat"; ?>
                </h5>

                <?php if (mysqli_num_rows($kategori) == 0) { ?>

                    <div class="alert alert-warning mb-0">
                        Belum ada kategori. Tambahkan kategori terlebih dahulu.
                    </div>

                <?php } else { ?>

                    <form method="POST">
                        <input
                            type="hidden"
                            name="id_alat"
                            value="<?= htmlspecialchars((string) $id_alat); ?>"
                        >

                        <div class="mb-3">
                            <label class="form-label">Nama Alat</label>
                            <input
                                type="text"
                                name="nama_alat"
                                class="form-control"
                                required
                                value="<?= htmlspecialchars($nama_alat); ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kategori</label>
                            <select
                                name="id_kategori"
                                class="form-select"
                                required
                            >
                                <option value="">-- Pilih Kategori --</option>

                                <?php
                                mysqli_data_seek($kategori, 0);

                                while ($kat = mysqli_fetch_assoc($kategori)) {
                                ?>
                                    <option
                                        value="<?= (int) $kat['id_kategori']; ?>"
                                        <?= $id_kategori == $kat['id_kategori']
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        <?= htmlspecialchars($kat['nama_kategori']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Tarif Sewa per Hari (Rp)
                            </label>
                            <input
                                type="number"
                                name="tarif_sewa"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                                value="<?= htmlspecialchars((string) $tarif_sewa); ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Stok Alat</label>
                            <input
                                type="number"
                                name="stok"
                                class="form-control"
                                min="0"
                                required
                                value="<?= htmlspecialchars((string) $stok); ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Kondisi Alat</label>
                            <select
                                name="kondisi"
                                class="form-select"
                                required
                            >
                                <?php
                                $daftar_kondisi = [
                                    'Baik',
                                    'Rusak Ringan',
                                    'Rusak Berat'
                                ];

                                foreach ($daftar_kondisi as $pilihan) {
                                ?>
                                    <option
                                        value="<?= htmlspecialchars($pilihan); ?>"
                                        <?= $kondisi == $pilihan
                                            ? 'selected'
                                            : ''; ?>
                                    >
                                        <?= htmlspecialchars($pilihan); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <button
                            type="submit"
                            name="simpan"
                            class="btn btn-hijau"
                        >
                            Simpan
                        </button>

                        <?php if ($id_alat != "") { ?>
                            <a
                                href="data_alat.php"
                                class="btn btn-secondary"
                            >
                                Batal
                            </a>
                        <?php } ?>
                    </form>

                <?php } ?>
            </div>

            <!-- DAFTAR ALAT -->
            <div class="kartu mt-4">
                <h5 class="judul mb-3">Daftar Alat</h5>

                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-8">
                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari nama alat atau kategori..."
                            value="<?= htmlspecialchars($cari); ?>"
                        >
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-hijau">
                            Cari
                        </button>

                        <a
                            href="data_alat.php"
                            class="btn btn-secondary"
                        >
                            Reset
                        </a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Alat</th>
                                <th>Kategori</th>
                                <th>Tarif/Hari</th>
                                <th>Stok</th>
                                <th>Kondisi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php
                        $no = 1;

                        if (mysqli_num_rows($data_alat) > 0) {
                            while ($alat = mysqli_fetch_assoc($data_alat)) {
                        ?>
                            <tr>
                                <td><?= $no++; ?></td>

                                <td>
                                    <?= htmlspecialchars($alat['nama_alat']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($alat['nama_kategori']); ?>
                                </td>

                                <td>
                                    Rp <?= number_format(
                                        (float) $alat['tarif_sewa'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td><?= (int) $alat['stok']; ?></td>

                                <td>
                                    <?= htmlspecialchars($alat['kondisi']); ?>
                                </td>

                                <td>
                                    <a
                                        class="btn btn-sm btn-hijau"
                                        href="data_alat.php?edit=<?= (int) $alat['id_alat']; ?>"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        class="btn btn-sm btn-hapus"
                                        href="data_alat.php?hapus=<?= (int) $alat['id_alat']; ?>"
                                        onclick="return confirm('Yakin ingin menghapus alat ini?')"
                                    >
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
                                    Belum ada data alat.
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
