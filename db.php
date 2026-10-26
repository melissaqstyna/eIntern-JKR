<?php
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "db_intern_jkr"
);

if ($conn->connect_error) {
    die("Sambungan database gagal.");
}