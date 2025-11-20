<?php
require_once __DIR__ . '/../Models/DataRepository.php';

class DashboardVM {
    private $repository;

    public function __construct() {
        $this->repository = new DataRepository();
    }

    public function getDashboardData() {
        try {
            // 1. Ambil Semua Data
            $deliveryRaw = $this->repository->getDeliveryProgress();
            $locatorRaw = $this->repository->getLocatorMapping();
            $transitRaw = $this->repository->getInTransitSJ();
            $recentRaw  = $this->repository->getRecentReceivals();

            // 2. Logic Tambahan untuk Delivery Progress (Hitung In-Transit Gap)
            $deliveryFormatted = array_map(function($row) {
                $row['received'] = $row['received'] ?? 0; // Handle null
                $row['in_transit_qty'] = max(0, $row['departure'] - $row['received']);
                return $row;
            }, $deliveryRaw);

            // 3. Rewrite Image URL to use Local Proxy
            if ($recentRaw && !empty($recentRaw['received_image_url'])) {
                // Cek jika URL masih HTTP biasa
                if (strpos($recentRaw['received_image_url'], 'http://') === 0) {
                    // Ubah menjadi: proxy.php?url=http://...
                    $recentRaw['received_image_url'] = 'proxy.php?url=' . urlencode($recentRaw['received_image_url']);
                }
            }

            return [
                'status' => 'success',
                'server_time' => date('Y-m-d H:i:s'),
                'data' => [
                    'delivery_progress' => $deliveryFormatted,
                    'locator_mapping' => $locatorRaw,
                    'in_transit_sj' => $transitRaw,
                    'recent_delivery' => $recentRaw
                ]
            ];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
?>