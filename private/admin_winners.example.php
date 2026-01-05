<?php
// admin_winners.example.php
// A simple dashboard template to view winners.

$goldFile = __DIR__ . '/../data/winners.json';
$silverFile = __DIR__ . '/../data/winners_silver.json';

$goldWinners = file_exists($goldFile) ? json_decode(file_get_contents($goldFile), true) : [];
$silverWinners = file_exists($silverFile) ? json_decode(file_get_contents($silverFile), true) : [];

echo "<h1>Winners Dashboard</h1>";
// Add your display logic here
?>