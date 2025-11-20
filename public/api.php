<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Load ViewModel
require_once __DIR__ . '/../src/ViewModels/DashboardVM.php';

// Inisialisasi ViewModel
$vm = new DashboardVM();

// Ambil data yang sudah diproses
$response = $vm->getDashboardData();

// Set HTTP Response Code jika error
if ($response['status'] === 'error') {
    http_response_code(500);
}

// Output JSON
echo json_encode($response);
?>