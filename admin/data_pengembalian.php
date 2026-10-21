
<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['level']) || $_SESSION['level'] != 'admin') {
    header("Location: ../index.php");
    exit;
}

$pesan = "";
$denda_per_hari = 5000;
$denda_per_barang_rusak = 5000;

// Menghitung denda keterlambatan
function hitungDendaPengembalian($rencana, $aktual, $tarif) {
    $tanggal_rencana = new DateTime($rencana);
    $tanggal_aktual = new DateTime($aktual);

    $selisih = (int) $tanggal_rencana
        ->diff($tanggal_aktual)
        ->format('%r%a');

    $terlambat = max(0, $selisih);

    return [
        'terlambat' => $terlambat,
        'denda' => $terlambat * $tarif
    ];
}

// Mencatat aktivitas admin
function catatAktivitasAdmin($koneksi, $aktivitas) {
    $id_user = (int) ($_SESSION['id_user'] ?? 0);
    $aktivitas = mysqli_real_escape_string($koneksi, $aktivitas);

    $hasil = mysqli_query($koneksi, "
        INSERT INTO log_aktivitas (id_user, aktivitas, waktu)
        VALUES ($id_user, '$aktivitas', NOW())
    ");

    if (!$hasil) {
        throw new Exception("Gagal mencatat aktivitas admin!");
    }
}

// Memvalidasi tanggal
function tanggalValid($tanggal) {
    $obj = DateTime::createFromFormat('!Y-m-d', $tanggal);

    return $obj
        && $obj->format('Y-m-d') === $tanggal
        && $tanggal <= date('Y-m-d');
}

// CATAT PENGEMBALIAN
if (isset($_POST['simpan'])) {
    $id_peminjaman = (int) ($_POST['id_peminjaman'] ?? 0);
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $jumlah_rusak = filter_var(
        $_POST['jumlah_rusak'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!tanggalValid($tanggal_kembali)) {
        $pesan = "Tanggal pengembalian tidak valid!";
    } elseif (
        $jumlah_rusak === false ||
        $jumlah_rusak === null ||
        $jumlah_rusak < 0
    ) {
        $pesan = "Jumlah barang rusak tidak valid!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_query($koneksi, "
                SELECT tanggal_rencana_kembali, status
                FROM peminjaman
                WHERE id_peminjaman = $id_peminjaman
                FOR UPDATE
            ");

            if (!$cek || mysqli_num_rows($cek) == 0) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $pinjam = mysqli_fetch_assoc($cek);

            if ($pinjam['status'] != 'disetujui') {
                throw new Exception(
                    "Peminjaman ini tidak dapat dikembalikan!"
                );
            }

            $cek_kembali = mysqli_query($koneksi, "
                SELECT id_pengembalian
                FROM pengembalian
                WHERE id_peminjaman = $id_peminjaman
            ");

            if (!$cek_kembali || mysqli_num_rows($cek_kembali) > 0) {
                throw new Exception(
                    "Pengembalian sudah tercatat!"
                );
            }

            $detail = mysqli_query($koneksi, "
                SELECT id_alat, jumlah
                FROM detail_peminjaman
                WHERE id_peminjaman = $id_peminjaman
                FOR UPDATE
            ");

            if (!$detail || mysqli_num_rows($detail) == 0) {
                throw new Exception("Detail alat tidak ditemukan!");
            }

            $total_barang = 0;
            $daftar_detail = [];

            while ($d = mysqli_fetch_assoc($detail)) {
                $total_barang += (int) $d['jumlah'];
                $daftar_detail[] = $d;
            }

            if ($jumlah_rusak > $total_barang) {
                throw new Exception(
                    "Jumlah barang rusak melebihi jumlah barang yang dipinjam!"
                );
            }

            $hasil_denda = hitungDendaPengembalian(
                $pinjam['tanggal_rencana_kembali'],
                $tanggal_kembali,
                $denda_per_hari
            );

            $terlambat = $hasil_denda['terlambat'];
            $denda_telat = $hasil_denda['denda'];
            $denda_rusak = $jumlah_rusak * $denda_per_barang_rusak;
            $total_denda = $denda_telat + $denda_rusak;

            $kondisi_alat = $jumlah_rusak > 0 ? 'Rusak' : 'Baik';

            $catatan = "Jumlah barang dipinjam: $total_barang"
                . "; jumlah barang rusak: $jumlah_rusak"
                . "; denda keterlambatan: Rp"
                . number_format($denda_telat, 0, ',', '.')
                . "; denda kerusakan: Rp"
                . number_format($denda_rusak, 0, ',', '.');

            $tanggal_sql = mysqli_real_escape_string(
                $koneksi,
                $tanggal_kembali
            );
            $kondisi_sql = mysqli_real_escape_string(
                $koneksi,
                $kondisi_alat
            );
            $catatan_sql = mysqli_real_escape_string(
                $koneksi,
                $catatan
            );

            // Simpan pengembalian beserta jumlah barang rusak
            $simpan = mysqli_query($koneksi, "
                INSERT INTO pengembalian (
                    id_peminjaman,
                    tanggal_kembali,
                    terlambat_hari,
                    denda,
                    kondisi_alat,
                    catatan,
                    status,
                    jumlah_rusak
                ) VALUES (
                    $id_peminjaman,
                    '$tanggal_sql',
                    $terlambat,
                    $total_denda,
                    '$kondisi_sql',
                    '$catatan_sql',
                    'disetujui',
                    $jumlah_rusak
                )
            ");

            if (!$simpan) {
                throw new Exception(
                    "Gagal menyimpan pengembalian: "
                    . mysqli_error($koneksi)
                );
            }

            // Kembalikan stok alat ke sistem
            foreach ($daftar_detail as $d) {
                $id_alat = (int) $d['id_alat'];
                $jumlah = (int) $d['jumlah'];

                $stok = mysqli_query($koneksi, "
                    UPDATE alat
                    SET stok = stok + $jumlah
                    WHERE id_alat = $id_alat
                ");

                if (!$stok || mysqli_affected_rows($koneksi) != 1) {
                    throw new Exception(
                        "Gagal memperbarui stok alat!"
                    );
                }
            }

            $ubah = mysqli_query($koneksi, "
                UPDATE peminjaman
                SET status = 'dikembalikan'
                WHERE id_peminjaman = $id_peminjaman
                  AND status = 'disetujui'
            ");

            if (!$ubah || mysqli_affected_rows($koneksi) != 1) {
                throw new Exception(
                    "Gagal mengubah status peminjaman!"
                );
            }

            catatAktivitasAdmin(
                $koneksi,
                "Admin mencatat pengembalian ID $id_peminjaman"
            );

            mysqli_commit($koneksi);

            header("Location: data_pengembalian.php?pesan=sukses");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// UBAH DATA PENGEMBALIAN
if (isset($_POST['ubah_pengembalian'])) {
    $id_pengembalian = (int) ($_POST['id_pengembalian'] ?? 0);
    $tanggal_kembali = trim($_POST['tanggal_kembali'] ?? '');
    $jumlah_rusak = filter_var(
        $_POST['jumlah_rusak'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!tanggalValid($tanggal_kembali)) {
        $pesan = "Tanggal pengembalian tidak valid!";
    } elseif (
        $jumlah_rusak === false ||
        $jumlah_rusak === null ||
        $jumlah_rusak < 0
    ) {
        $pesan = "Jumlah barang rusak tidak valid!";
    } else {
        mysqli_begin_transaction($koneksi);

        try {
            $cek = mysqli_query($koneksi, "
                SELECT
                    pg.id_peminjaman,
                    p.tanggal_rencana_kembali
                FROM pengembalian pg
                JOIN peminjaman p
                    ON pg.id_peminjaman = p.id_peminjaman
                WHERE pg.id_pengembalian = $id_pengembalian
                FOR UPDATE
            ");

            if (!$cek || mysqli_num_rows($cek) == 0) {
                throw new Exception(
                    "Data pengembalian tidak ditemukan!"
                );
            }

            $row = mysqli_fetch_assoc($cek);
            $id_peminjaman = (int) $row['id_peminjaman'];

            $cek_detail = mysqli_query($koneksi, "
                SELECT COALESCE(SUM(jumlah), 0) AS total_barang
                FROM detail_peminjaman
                WHERE id_peminjaman = $id_peminjaman
            ");

            if (!$cek_detail) {
                throw new Exception("Gagal mengambil detail alat!");
            }

            $total_barang = (int) mysqli_fetch_assoc(
                $cek_detail
            )['total_barang'];

            if ($jumlah_rusak > $total_barang) {
                throw new Exception(
                    "Jumlah barang rusak melebihi jumlah barang yang dipinjam!"
                );
            }

            $hasil_denda = hitungDendaPengembalian(
                $row['tanggal_rencana_kembali'],
                $tanggal_kembali,
                $denda_per_hari
            );

            $terlambat = $hasil_denda['terlambat'];
            $denda_telat = $hasil_denda['denda'];
            $denda_rusak = $jumlah_rusak * $denda_per_barang_rusak;
            $total_denda = $denda_telat + $denda_rusak;

            $kondisi_alat = $jumlah_rusak > 0 ? 'Rusak' : 'Baik';

            $catatan = "Jumlah barang dipinjam: $total_barang"
                . "; jumlah barang rusak: $jumlah_rusak"
                . "; denda keterlambatan: Rp"
                . number_format($denda_telat, 0, ',', '.')
                . "; denda kerusakan: Rp"
                . number_format($denda_rusak, 0, ',', '.');

            $tanggal_sql = mysqli_real_escape_string(
                $koneksi,
                $tanggal_kembali
            );
            $kondisi_sql = mysqli_real_escape_string(
                $koneksi,
                $kondisi_alat
            );
            $catatan_sql = mysqli_real_escape_string(
                $koneksi,
                $catatan
            );

            $ubah = mysqli_query($koneksi, "
                UPDATE pengembalian
                SET tanggal_kembali = '$tanggal_sql',
                    terlambat_hari = $terlambat,
                    denda = $total_denda,
                    kondisi_alat = '$kondisi_sql',
                    catatan = '$catatan_sql',
                    jumlah_rusak = $jumlah_rusak
                WHERE id_pengembalian = $id_pengembalian
            ");

            if (!$ubah) {
                throw new Exception(
                    "Gagal mengubah data pengembalian: "
                    . mysqli_error($koneksi)
                );
            }

            catatAktivitasAdmin(
                $koneksi,
                "Admin mengubah pengembalian ID $id_pengembalian"
            );

            mysqli_commit($koneksi);

            header("Location: data_pengembalian.php?pesan=ubah");
            exit;

        } catch (Throwable $e) {
            mysqli_rollback($koneksi);
            $pesan = $e->getMessage();
        }
    }
}

// HAPUS DATA PENGEMBALIAN
if (isset($_POST['hapus_pengembalian'])) {
    $id_pengembalian = (int) ($_POST['id_pengembalian'] ?? 0);

    mysqli_begin_transaction($koneksi);

    try {
        $cek = mysqli_query($koneksi, "
            SELECT id_peminjaman
            FROM pengembalian
            WHERE id_pengembalian = $id_pengembalian
            FOR UPDATE
        ");

        if (!$cek || mysqli_num_rows($cek) == 0) {
            throw new Exception(
                "Data pengembalian tidak ditemukan!"
            );
        }

        $row = mysqli_fetch_assoc($cek);
        $id_peminjaman = (int) $row['id_peminjaman'];

        $cek_pinjam = mysqli_query($koneksi, "
            SELECT status
            FROM peminjaman
            WHERE id_peminjaman = $id_peminjaman
            FOR UPDATE
        ");

        if (!$cek_pinjam || mysqli_num_rows($cek_pinjam) == 0) {
            throw new Exception("Data peminjaman tidak ditemukan!");
        }

        $pinjam = mysqli_fetch_assoc($cek_pinjam);

        if ($pinjam['status'] == 'dikembalikan') {
            $detail = mysqli_query($koneksi, "
                SELECT id_alat, jumlah
                FROM detail_peminjaman
                WHERE id_peminjaman = $id_peminjaman
            ");

            if (!$detail) {
                throw new Exception("Gagal mengambil detail alat!");
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
                        "Stok alat tidak mencukupi!"
                    );
                }
            }

            $ubah = mysqli_query($koneksi, "
                UPDATE peminjaman
                SET status = 'disetujui'
                WHERE id_peminjaman = $id_peminjaman
            ");

            if (!$ubah) {
                throw new Exception(
                    "Gagal mengubah status peminjaman!"
                );
            }
        }

        $hapus = mysqli_query($koneksi, "
            DELETE FROM pengembalian
            WHERE id_pengembalian = $id_pengembalian
        ");

        if (!$hapus) {
            throw new Exception(
                "Gagal menghapus data pengembalian!"
            );
        }

        catatAktivitasAdmin(
            $koneksi,
            "Admin menghapus pengembalian ID $id_pengembalian"
        );

        mysqli_commit($koneksi);

        header("Location: data_pengembalian.php?pesan=hapus");
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        $pesan = $e->getMessage();
    }
}

// PESAN NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] == 'sukses') {
        $pesan = "Pengembalian berhasil dicatat!";
    } elseif ($_GET['pesan'] == 'ubah') {
        $pesan = "Data pengembalian berhasil diubah!";
    } elseif ($_GET['pesan'] == 'hapus') {
        $pesan = "Data pengembalian berhasil dihapus!";
    }
}

// DAFTAR PEMINJAMAN YANG BELUM DIKEMBALIKAN
$tersedia = mysqli_query($koneksi, "
    SELECT
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            ORDER BY a.nama_alat SEPARATOR ', '
        ) AS daftar_alat,
        COALESCE(SUM(dp.jumlah), 0) AS total_barang
    FROM peminjaman p
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a ON dp.id_alat = a.id_alat
    LEFT JOIN pengembalian pg
        ON p.id_peminjaman = pg.id_peminjaman
    WHERE p.status = 'disetujui'
      AND pg.id_pengembalian IS NULL
    GROUP BY
        p.id_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama
    ORDER BY p.id_peminjaman DESC
");

// RIWAYAT PENGEMBALIAN
$data = mysqli_query($koneksi, "
    SELECT
        pg.id_pengembalian,
        pg.id_peminjaman,
        pg.tanggal_kembali,
        pg.terlambat_hari,
        pg.denda,
        pg.kondisi_alat,
        pg.catatan,
        pg.status,
        pg.jumlah_rusak,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama,
        GROUP_CONCAT(
            CONCAT(a.nama_alat, ' (', dp.jumlah, ')')
            ORDER BY a.nama_alat SEPARATOR ', '
        ) AS daftar_alat
    FROM pengembalian pg
    JOIN peminjaman p
        ON pg.id_peminjaman = p.id_peminjaman
    JOIN users u ON p.id_user = u.id_user
    LEFT JOIN detail_peminjaman dp
        ON p.id_peminjaman = dp.id_peminjaman
    LEFT JOIN alat a ON dp.id_alat = a.id_alat
    GROUP BY
        pg.id_pengembalian,
        pg.id_peminjaman,
        pg.tanggal_kembali,
        pg.terlambat_hari,
        pg.denda,
        pg.kondisi_alat,
        pg.catatan,
        pg.status,
        pg.jumlah_rusak,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        u.nama
    ORDER BY pg.id_pengembalian DESC
");

// DATA UNTUK FORM EDIT
$edit_data = null;

if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];

    $hasil_edit = mysqli_query($koneksi, "
        SELECT
            pg.id_pengembalian,
            pg.tanggal_kembali,
            pg.jumlah_rusak,
            p.id_peminjaman
        FROM pengembalian pg
        JOIN peminjaman p
            ON pg.id_peminjaman = p.id_peminjaman
        WHERE pg.id_pengembalian = $id_edit
        LIMIT 1
    ");

    if ($hasil_edit && mysqli_num_rows($hasil_edit) > 0) {
        $edit_data = mysqli_fetch_assoc($hasil_edit);

        $hasil_jumlah = mysqli_query($koneksi, "
            SELECT COALESCE(SUM(jumlah), 0) AS total_barang
            FROM detail_peminjaman
            WHERE id_peminjaman = "
                . (int) $edit_data['id_peminjaman']
        );

        if ($hasil_jumlah) {
            $edit_data['total_barang'] = (int) mysqli_fetch_assoc(
                $hasil_jumlah
            )['total_barang'];
        } else {
            $edit_data['total_barang'] = 0;
        }
    }
}

// CEK QUERY SEBELUM MENAMPILKAN HALAMAN
if (!$tersedia || !$data) {
    die("Gagal mengambil data: " . mysqli_error($koneksi));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Pengembalian - KEBUN PINJAM</title>

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
            <a href="data_peminjaman.php">Data Penyewaan</a>
            <a href="data_pengembalian.php" class="menu-aktif">
                Data Pengembalian
            </a>
            <a href="laporan.php">Laporan</a>
            <a href="log_aktivitas.php">Log Aktivitas</a>
            <a href="../logout.php">Logout</a>
        </div>

        <!-- KONTEN -->
        <div class="col-md-9 col-lg-10 konten">

            <h3 class="judul">Data Pengembalian</h3>

            <p class="text-secondary mb-4">
                Selamat datang,
                <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?>!
            </p>

            <?php if ($pesan != '') { ?>
                <div class="alert alert-info">
                    <?= htmlspecialchars($pesan); ?>
                </div>
            <?php } ?>

            <!-- FORM UBAH PENGEMBALIAN -->
            <?php if ($edit_data) { ?>
                <div class="kartu mb-4">
                    <h5 class="judul mb-3">
                        Ubah Data Pengembalian
                    </h5>

                    <form method="POST">
                        <input
                            type="hidden"
                            name="id_pengembalian"
                            value="<?= (int) $edit_data['id_pengembalian']; ?>"
                        >

                        <div class="mb-3">
                            <label class="form-label">
                                Tanggal Dikembalikan
                            </label>

                            <input
                                type="date"
                                name="tanggal_kembali"
                                class="form-control"
                                required
                                max="<?= date('Y-m-d'); ?>"
                                value="<?= htmlspecialchars(
                                    $edit_data['tanggal_kembali']
                                ); ?>"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Jumlah Barang Rusak
                            </label>

                            <input
                                type="number"
                                name="jumlah_rusak"
                                class="form-control"
                                min="0"
                                max="<?= (int) $edit_data['total_barang']; ?>"
                                value="<?= (int) $edit_data['jumlah_rusak']; ?>"
                                required
                            >

                            <small class="text-secondary">
                                Jumlah barang dipinjam:
                                <?= (int) $edit_data['total_barang']; ?> barang.
                                Denda Rp5.000 per barang rusak.
                            </small>
                        </div>

                        <button
                            type="submit"
                            name="ubah_pengembalian"
                            class="btn btn-hijau"
                        >
                            Simpan Perubahan
                        </button>

                        <a
                            href="data_pengembalian.php"
                            class="btn btn-secondary"
                        >
                            Batal
                        </a>
                    </form>
                </div>
            <?php } ?>

            <!-- FORM CATAT PENGEMBALIAN -->
            <div class="kartu mb-4">
                <h5 class="judul mb-3">
                    Catat Pengembalian Alat
                </h5>

                <p class="text-secondary">
                    Periksa alat yang diterima, lalu masukkan jumlah
                    barang yang rusak.
                </p>

                <?php if (mysqli_num_rows($tersedia) > 0) { ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Peminjam</th>
                                    <th>Daftar Alat</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Rencana Kembali</th>
                                    <th>Form Pengembalian</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php
                            $no = 1;

                            while ($p = mysqli_fetch_assoc($tersedia)) {
                            ?>
                                <tr>
                                    <td><?= $no++; ?></td>

                                    <td>
                                        <?= htmlspecialchars($p['nama']); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $p['daftar_alat'] ?? '-'
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $p['tanggal_pinjam']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $p['tanggal_rencana_kembali']
                                        ); ?>
                                    </td>

                                    <td style="min-width:250px">
                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Pastikan alat sudah diterima. Catat pengembalian?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id_peminjaman"
                                                value="<?= (int) $p['id_peminjaman']; ?>"
                                            >

                                            <label class="form-label">
                                                Tanggal Kembali
                                            </label>

                                            <input
                                                type="date"
                                                name="tanggal_kembali"
                                                class="form-control mb-2"
                                                value="<?= date('Y-m-d'); ?>"
                                                max="<?= date('Y-m-d'); ?>"
                                                required
                                            >

                                            <label class="form-label">
                                                Jumlah Barang Rusak
                                            </label>

                                            <input
                                                type="number"
                                                name="jumlah_rusak"
                                                class="form-control mb-2"
                                                min="0"
                                                max="<?= (int) $p['total_barang']; ?>"
                                                value="0"
                                                required
                                            >

                                            <div class="small text-secondary mb-2">
                                                Maksimal
                                                <?= (int) $p['total_barang']; ?>
                                                barang. Denda kerusakan
                                                Rp5.000/barang dan denda
                                                keterlambatan Rp5.000/hari.
                                            </div>

                                            <button
                                                type="submit"
                                                name="simpan"
                                                class="btn btn-hijau btn-sm"
                                            >
                                                Catat Pengembalian
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <p class="text-secondary mb-0">
                        Tidak ada peminjaman yang menunggu pengembalian.
                    </p>
                <?php } ?>
            </div>

            <!-- RIWAYAT PENGEMBALIAN -->
            <div class="kartu">
                <h5 class="judul mb-3">
                    Riwayat Pengembalian
                </h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Peminjam</th>
                                <th>Daftar Alat</th>
                                <th>Tanggal Pinjam</th>
                                <th>Rencana Kembali</th>
                                <th>Tanggal Kembali</th>
                                <th>Terlambat</th>
                                <th>Jumlah Rusak</th>
                                <th>Denda Total</th>
                                <th>Catatan</th>
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

                                <td>
                                    <?= htmlspecialchars($row['nama']); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['daftar_alat'] ?? '-'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['tanggal_pinjam']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['tanggal_rencana_kembali']
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['tanggal_kembali']
                                    ); ?>
                                </td>

                                <td>
                                    <?= (int) $row['terlambat_hari']; ?>
                                    hari
                                </td>

                                <td>
                                    <?= (int) $row['jumlah_rusak']; ?>
                                    barang
                                </td>

                                <td>
                                    Rp<?= number_format(
                                        (float) $row['denda'],
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row['catatan'] ?? '-'
                                    ); ?>
                                </td>

                                <td>
                                    <div class="d-flex gap-2">
                                        <a
                                            href="data_pengembalian.php?edit=<?= (int) $row['id_pengembalian']; ?>"
                                            class="btn btn-hijau btn-sm"
                                        >
                                            Ubah
                                        </a>

                                        <form
                                            method="POST"
                                            onsubmit="return confirm('Yakin ingin menghapus catatan pengembalian?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id_pengembalian"
                                                value="<?= (int) $row['id_pengembalian']; ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="hapus_pengembalian"
                                                class="btn btn-hapus btn-sm"
                                            >
                                                Hapus
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            }
                        } else {
                        ?>
                            <tr>
                                <td colspan="11" class="text-center">
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
