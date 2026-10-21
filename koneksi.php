
<?php
$koneksi = mysqli_connect(
    "localhost",
    "root",
    "",
    "db_pekebun"
);

if (!$koneksi) {
    die("Koneksi database gagal!");
}
?>
