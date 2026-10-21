<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$id = "";
$nama = "";
$username = "";
$level = "peminjam";
$status = "aktif";

// Simpan data pengguna
if (isset($_POST['simpan'])) {
    $id = (int) $_POST['id_user'];
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $level = $_POST['level'];
    $status = $_POST['status'];
    $password = $_POST['password'];

    if ($nama == "" || $username == "") {
        $pesan = "Nama dan username harus diisi!";
    } elseif (!in_array($level, ['admin', 'petugas', 'peminjam']) ||
              !in_array($status, ['aktif', 'nonaktif'])) {
        $pesan = "Level atau status tidak valid!";
    } else {
        $nama_sql = mysqli_real_escape_string($koneksi, $nama);
        $username_sql = mysqli_real_escape_string($koneksi, $username);

        $cek = mysqli_query($koneksi, "
            SELECT id_user FROM users
            WHERE username='$username_sql' AND id_user!='$id'
        ");

        if (mysqli_num_rows($cek) > 0) {
            $pesan = "Username sudah digunakan!";
        } elseif ($id == 0 && $password == "") {
            $pesan = "Password harus diisi!";
        } else {
            if ($id > 0) {
                // Akun yang sedang digunakan tidak boleh kehilangan level admin
                if ($id == $_SESSION['id_user']) {
                    $level = 'admin';
                    $status = 'aktif';
                }

                $level_sql = mysqli_real_escape_string($koneksi, $level);
                $status_sql = mysqli_real_escape_string($koneksi, $status);

                if ($password != "") {
                    $password_sql = md5($password);

                    $sql = "UPDATE users SET
                        nama='$nama_sql',
                        username='$username_sql',
                        password='$password_sql',
                        level='$level_sql',
                        status='$status_sql'
                        WHERE id_user='$id'";
                } else {
                    $sql = "UPDATE users SET
                        nama='$nama_sql',
                        username='$username_sql',
                        level='$level_sql',
                        status='$status_sql'
                        WHERE id_user='$id'";
                }
            } else {
                $password_sql = md5($password);
                $level_sql = mysqli_real_escape_string($koneksi, $level);
                $status_sql = mysqli_real_escape_string($koneksi, $status);

                $sql = "INSERT INTO users
                    (nama, username, password, level, status)
                    VALUES (
                        '$nama_sql',
                        '$username_sql',
                        '$password_sql',
                        '$level_sql',
                        '$status_sql'
                    )";
            }

            if (mysqli_query($koneksi, $sql)) {
                header("Location: data_pengguna.php?pesan=sukses");
                exit;
            } else {
                $pesan = "Data pengguna gagal disimpan!";
            }
        }
    }
}

// Ambil data untuk diedit
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];

    $hasil = mysqli_query($koneksi,
        "SELECT * FROM users WHERE id_user='$id'"
    );

    $user = mysqli_fetch_assoc($hasil);

    if ($user) {
        $nama = $user['nama'];
        $username = $user['username'];
        $level = $user['level'];
        $status = $user['status'];
    } else {
        header("Location: data_pengguna.php");
        exit;
    }
}

// Hapus pengguna
if (isset($_GET['hapus'])) {
    $id_hapus = (int) $_GET['hapus'];

    if ($id_hapus == $_SESSION['id_user']) {
        $pesan = "Akun yang sedang digunakan tidak bisa dihapus!";
    } else {
        $cek = mysqli_query($koneksi, "
            SELECT id_peminjaman
            FROM peminjaman
            WHERE id_user='$id_hapus'
            LIMIT 1
        ");

        if (!$cek) {
            $pesan = "Gagal memeriksa riwayat peminjaman!";
        } elseif (mysqli_num_rows($cek) > 0) {
            $pesan = "Pengguna masih memiliki riwayat peminjaman!";
        } else {
            $hapus = mysqli_query($koneksi, "
                DELETE FROM users
                WHERE id_user='$id_hapus'
                AND level!='admin'
            ");

            if ($hapus && mysqli_affected_rows($koneksi) > 0) {
                header("Location: data_pengguna.php?pesan=hapus");
                exit;
            } else {
                $pesan = "Pengguna gagal dihapus!";
            }
        }
    }
}

// Ubah status pengguna
if (isset($_GET['ubah'])) {
    $id_ubah = (int) $_GET['ubah'];

    if ($id_ubah != $_SESSION['id_user']) {
        mysqli_query($koneksi, "
            UPDATE users
            SET status=IF(status='aktif','nonaktif','aktif')
            WHERE id_user='$id_ubah'
            AND level!='admin'
        ");
    }

    header("Location: data_pengguna.php");
    exit;
}

// Pesan berhasil
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'sukses') {
        $pesan = "Data pengguna berhasil disimpan!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Data pengguna berhasil dihapus!";
    }
}

// Ambil semua pengguna
$data = mysqli_query($koneksi,
    "SELECT * FROM users ORDER BY id_user DESC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Data Pengguna - KEBUN PINJAM</title>

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
            <a href="data_pengguna.php" class="menu-aktif">Data Pengguna</a>
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php">Data Pengembalian</a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Data Pengguna</h3>

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

            <!-- FORM PENGGUNA -->
            <div class="kartu mb-4">

                <h5 class="judul mb-3">
                    <?= $id > 0 ? 'Edit Pengguna' : 'Tambah Pengguna'; ?>
                </h5>

                <form method="POST" action="data_pengguna.php">

                    <input type="hidden" name="id_user" value="<?= (int)$id; ?>">

                    <div class="mb-3">
                        <label class="form-label">Nama</label>
                        <input type="text" name="nama" class="form-control"
                               value="<?= htmlspecialchars($nama); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control"
                               value="<?= htmlspecialchars($username); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Password
                            <?= $id > 0 ? '(kosongkan jika tidak diganti)' : ''; ?>
                        </label>
                        <input type="password" name="password" class="form-control"
                               <?= $id == 0 ? 'required' : ''; ?>>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Level</label>
                        <select name="level" class="form-select" required>
                            <option value="admin" <?= $level == 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="petugas" <?= $level == 'petugas' ? 'selected' : ''; ?>>Petugas</option>
                            <option value="peminjam" <?= $level == 'peminjam' ? 'selected' : ''; ?>>Peminjam</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" required>
                            <option value="aktif" <?= $status == 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?= $status == 'nonaktif' ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>

                    <button type="submit" name="simpan" class="btn btn-hijau">
                        Simpan
                    </button>

                    <?php if ($id > 0) { ?>
                        <a href="data_pengguna.php" class="btn btn-secondary">
                            Batal
                        </a>
                    <?php } ?>

                </form>
            </div>

            <!-- DAFTAR PENGGUNA -->
            <div class="kartu mt-4">

                <h5 class="judul mb-3">Daftar Pengguna</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">

                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama</th>
                                <th>Username</th>
                                <th>Level</th>
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
                                    <td><?= htmlspecialchars($row['nama']); ?></td>
                                    <td><?= htmlspecialchars($row['username']); ?></td>
                                    <td><?= ucfirst(htmlspecialchars($row['level'])); ?></td>
                                    <td><?= ucfirst(htmlspecialchars($row['status'])); ?></td>

                                    <td>
                                        <a class="btn btn-sm btn-hijau"
                                           href="data_pengguna.php?edit=<?= $row['id_user']; ?>">
                                            Edit
                                        </a>

                                        <?php if (
                                            $row['level'] != 'admin' &&
                                            $row['id_user'] != $_SESSION['id_user']
                                        ) { ?>

                                            <a class="btn btn-sm btn-secondary"
                                               href="data_pengguna.php?ubah=<?= $row['id_user']; ?>"
                                               onclick="return confirm('Yakin ingin mengubah status pengguna?')">
                                                Status
                                            </a>

                                            <a class="btn btn-sm btn-hapus"
                                               href="data_pengguna.php?hapus=<?= $row['id_user']; ?>"
                                               onclick="return confirm('Yakin ingin menghapus pengguna?')">
                                                Hapus
                                            </a>

                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                            ?>
                                <tr>
                                    <td colspan="6" class="text-center">
                                        Belum ada data pengguna.
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