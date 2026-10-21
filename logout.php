
<?php
session_start();
include "koneksi.php";

// Catat aktivitas logout jika pengguna sudah login
if (isset($_SESSION['id_user'])) {
    $id_user = (int) $_SESSION['id_user'];
    $aktivitas = "Logout dari sistem";

    mysqli_query($koneksi, "INSERT INTO log_aktivitas
        (id_user, aktivitas, waktu)
        VALUES ($id_user, '$aktivitas', NOW())");
}

// Hapus sesi pengguna
session_unset();
session_destroy();

header("Location: index.php?pesan=logout");
exit;
?>
