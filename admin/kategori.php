
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$id = "";
$nama_kategori = "";
$cari = "";

// Simpan kategori
if (isset($_POST['simpan'])) {
    $id = (int) $_POST['id_kategori'];
    $nama_kategori = mysqli_real_escape_string(
        $koneksi,
        trim($_POST['nama_kategori'])
    );

    $cek = mysqli_query($koneksi, "
        SELECT id_kategori FROM kategori
        WHERE nama_kategori='$nama_kategori'
        AND id_kategori!='$id'
    ");

    if (mysqli_num_rows($cek) > 0) {
        $pesan = "Nama kategori sudah digunakan!";
    } else {
        if ($id > 0) {
            $sql = "UPDATE kategori
                    SET nama_kategori='$nama_kategori'
                    WHERE id_kategori='$id'";
        } else {
            $sql = "INSERT INTO kategori (nama_kategori)
                    VALUES ('$nama_kategori')";
        }

        if (mysqli_query($koneksi, $sql)) {
            header("Location: kategori.php?pesan=sukses");
            exit;
        } else {
            $pesan = "Kategori gagal disimpan!";
        }
    }
}

// Pesan setelah menyimpan
if (isset($_GET['pesan']) && $_GET['pesan'] == 'sukses') {
    $pesan = "Kategori berhasil disimpan!";
}

// Ambil data kategori untuk diedit
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $hasil = mysqli_query($koneksi, "
        SELECT * FROM kategori WHERE id_kategori='$id'
    ");

    $kategori_edit = mysqli_fetch_assoc($hasil);

    if ($kategori_edit) {
        $nama_kategori = $kategori_edit['nama_kategori'];
    } else {
        header("Location: kategori.php");
        exit;
    }
}

// Hapus kategori
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];

    $cek = mysqli_query($koneksi, "
        SELECT id_alat FROM alat
        WHERE id_kategori='$id_hapus'
    ");

    if (mysqli_num_rows($cek) > 0) {
        $pesan = "Kategori masih digunakan oleh data alat!";
    } else {
        if (mysqli_query($koneksi, "
            DELETE FROM kategori WHERE id_kategori='$id_hapus'
        ")) {
            header("Location: kategori.php?pesan=hapus");
            exit;
        } else {
            $pesan = "Kategori gagal dihapus!";
        }
    }
}

// Pesan setelah menghapus
if (isset($_GET['pesan']) && $_GET['pesan'] == 'hapus') {
    $pesan = "Kategori berhasil dihapus!";
}

// Pencarian kategori atau nama alat
if (isset($_GET['cari'])) {
    $cari = mysqli_real_escape_string($koneksi, $_GET['cari']);
}

// Ambil kategori beserta nama alat
$data = mysqli_query($koneksi, "
    SELECT kategori.*,
        GROUP_CONCAT(
            DISTINCT alat.nama_alat
            ORDER BY alat.nama_alat
            SEPARATOR ', '
        ) AS nama_alat
    FROM kategori
    LEFT JOIN alat ON kategori.id_kategori = alat.id_kategori
    WHERE kategori.nama_kategori LIKE '%$cari%'
       OR alat.nama_alat LIKE '%$cari%'
    GROUP BY kategori.id_kategori
    ORDER BY kategori.id_kategori DESC
");
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Kategori - KEBUN PINJAM</title>

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
            <a href="kategori.php" class="menu-aktif">Data Kategori</a>
            <a href="data_alat.php">Data Alat</a>
            <a href="data_pengguna.php">Data Pengguna</a>
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Data Kategori</h3>
            <p class="text-secondary">Kelola kategori alat berkebun.</p>

            <!-- PESAN -->
            <?php if ($pesan != "") { ?>
                <div class="alert alert-success py-2">
                    <?= htmlspecialchars($pesan); ?>
                </div>
            <?php } ?>

            <!-- FORM TAMBAH / EDIT -->
            <div class="kartu mb-4">
                <h5 class="judul mb-3">
                    <?= $id > 0 ? 'Edit Kategori' : 'Tambah Kategori'; ?>
                </h5>

                <form method="POST" action="kategori.php">
                    <input type="hidden" name="id_kategori"
                        value="<?= $id; ?>">

                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input
                            type="text"
                            name="nama_kategori"
                            class="form-control"
                            placeholder="Masukkan nama kategori"
                            value="<?= htmlspecialchars($nama_kategori); ?>"
                            required
                        >
                    </div>

                    <button type="submit" name="simpan"
                        class="btn btn-hijau">
                        Simpan
                    </button>

                    <?php if ($id > 0) { ?>
                        <a href="kategori.php" class="btn btn-secondary">
                            Batal
                        </a>
                    <?php } ?>
                </form>
            </div>

            <!-- DAFTAR KATEGORI -->
            <div class="kartu">
                <h5 class="judul mb-3">Daftar Kategori</h5>

                <!-- PENCARIAN -->
                <form method="GET" action="kategori.php"
                    class="row g-2 mb-3">

                    <div class="col-md-6">
                        <input
                            type="text"
                            name="cari"
                            class="form-control"
                            placeholder="Cari kategori atau nama alat..."
                            value="<?= htmlspecialchars($cari); ?>"
                        >
                    </div>

                    <div class="col-auto">
                        <button type="submit" class="btn btn-hijau">
                            Cari
                        </button>
                    </div>

                    <?php if ($cari != "") { ?>
                        <div class="col-auto">
                            <a href="kategori.php"
                                class="btn btn-secondary">
                                Reset
                            </a>
                        </div>
                    <?php } ?>
                </form>

                <!-- TABEL -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Nama Kategori</th>
                                <th>Alat yang Termasuk</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $no = 1; ?>

                            <?php while ($row = mysqli_fetch_assoc($data)) { ?>
                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td>
                                        <?= htmlspecialchars($row['nama_kategori']); ?>
                                    </td>

                                    <td>
                                        <?= !empty($row['nama_alat'])
                                            ? htmlspecialchars($row['nama_alat'])
                                            : 'Belum ada alat'; ?>
                                    </td>

                                    <td>
                                        <a
                                            href="kategori.php?edit=<?= $row['id_kategori']; ?>"
                                            class="btn btn-sm btn-warning">
                                            Edit
                                        </a>

                                        <a
                                            href="kategori.php?hapus=<?= $row['id_kategori']; ?>"
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Yakin ingin menghapus kategori ini?')">
                                            Hapus
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>

                            <?php if (mysqli_num_rows($data) == 0) { ?>
                                <tr>
                                    <td colspan="4"
                                        class="text-center text-secondary">
                                        Kategori tidak ditemukan.
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
