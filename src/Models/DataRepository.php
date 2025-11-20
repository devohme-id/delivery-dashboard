<?php
require_once __DIR__ . '/../../config/database.php';

class DataRepository {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getDeliveryProgress() {
        // UPDATE: Menambahkan CTE 'received_total' dan join ke main query
        $query = "
        WITH plan_data AS (
          SELECT
            DATE(pst) AS pst, demand_wo, pn, assembly_partno AS model, sequence, SUM(qty) AS plan
          FROM (
              SELECT pst, demand_wo, pn, assembly_partno, s1 AS qty, 'AGING' AS sequence FROM prodsys2_delivery_fc_tbl WHERE feeding_time = '06:00:00' AND s1 > 0
              UNION ALL
              SELECT pst, demand_wo, pn, assembly_partno, s1 AS qty, 'SESSION 1' AS sequence FROM prodsys2_delivery_fc_tbl WHERE s1 > 0 AND s2 = 0 AND feeding_time <> '06:00:00'
              UNION ALL
              SELECT pst, demand_wo, pn, assembly_partno, s2 AS qty, 'SESSION 2' AS sequence FROM prodsys2_delivery_fc_tbl WHERE s1 = 0 AND s2 > 0 AND feeding_time <> '06:00:00'
              UNION ALL
              SELECT pst, demand_wo, pn, assembly_partno, qty AS qty, 'SESSION 2' AS sequence FROM prodsys2_delivery_fc_tbl WHERE s1 = 0 AND s2 = 0 AND qty > 0 AND feeding_time <> '06:00:00'
              UNION ALL
              SELECT pst, demand_wo, pn, assembly_partno, s1 AS qty, 'SESSION 1' AS sequence FROM prodsys2_delivery_fc_tbl WHERE s1 > 0 AND s2 > 0 AND feeding_time <> '06:00:00'
              UNION ALL
              SELECT pst, demand_wo, pn, assembly_partno, s2 AS qty, 'SESSION 2' AS sequence FROM prodsys2_delivery_fc_tbl WHERE s1 > 0 AND s2 > 0 AND feeding_time <> '06:00:00'
            ) AS raw_plan
          GROUP BY DATE(pst), demand_wo, pn, assembly_partno, sequence
        ),
        departure_total AS (
          SELECT DATE(pst) AS pst, demand_wo, pn, model, SUM(departure_qty) AS total_departure
          FROM prodsys2_delivery_departure_temp_tbl
          WHERE flag_del = 'Y'
          GROUP BY DATE(pst), demand_wo, pn, model
        ),
        -- NEW: Data Receiving
        received_total AS (
          SELECT DATE(pst) AS pst, demand_wo, pn, model, SUM(departure_qty) AS total_received
          FROM prodsys2_delivery_received_tbl
          WHERE flag_del = 'Y'
          GROUP BY DATE(pst), demand_wo, pn, model
        ),
        allocated AS (
          SELECT
            p.pst, p.demand_wo, p.pn, p.model, p.sequence, p.plan,
            IFNULL(d.total_departure, 0) as total_departure,
            IFNULL(r.total_received, 0) as total_received,
            CASE
              WHEN p.sequence = 'SESSION 1' THEN LEAST(p.plan, IFNULL(d.total_departure, 0))
              WHEN p.sequence = 'SESSION 2' THEN GREATEST(IFNULL(d.total_departure, 0) - IFNULL((SELECT plan FROM plan_data WHERE sequence = 'SESSION 1' AND pst = p.pst AND demand_wo = p.demand_wo AND pn = p.pn AND model = p.model), 0), 0)
              ELSE LEAST(p.plan, IFNULL(d.total_departure, 0))
            END AS allocated_departure
          FROM plan_data p
          LEFT JOIN departure_total d ON d.pst = p.pst AND d.demand_wo = p.demand_wo AND d.pn = p.pn AND d.model = p.model
          LEFT JOIN received_total r ON r.pst = p.pst AND r.demand_wo = p.demand_wo AND r.pn = p.pn AND r.model = p.model
        )
        SELECT
          DATE_FORMAT(pst, '%d-%b') AS pst_display,
          pst AS pst_raw,
          sequence,
          SUM(plan) AS plan,
          SUM(allocated_departure) AS departure,
          -- Logic Distribusi Received mengikuti proporsi Plan
          LEAST(SUM(allocated_departure), SUM(total_received)) AS received,
          CONCAT(ROUND(SUM(allocated_departure) / NULLIF(SUM(plan), 0) * 100, 1), '%') AS rate
        FROM allocated
        GROUP BY pst, sequence
        ORDER BY pst, FIELD(sequence, 'AGING', 'SESSION 1', 'SESSION 2')
        LIMIT 6;
        ";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLocatorMapping() {
        // Query Existing (WIP Locator)
        $query = "
        WITH total_qty AS (
            SELECT demand_wo, pn, model, lot, SUM(qty) AS total_qty
            FROM stockflow_system.prodsys2_delivery_locator_fg_tbl
            WHERE state = 'WIP-C'
            GROUP BY demand_wo, pn, model, lot
        )
        SELECT
            a.locator,
            DATE_FORMAT(STR_TO_DATE(a.pst, '%Y-%m-%d'), '%d-%b') AS pst,
            a.org, a.demand_wo, a.pn, a.model, a.lot, a.oqc, a.qty,
            (t.total_qty - a.lot) AS remain_del
        FROM stockflow_system.prodsys2_delivery_locator_fg_tbl a
        JOIN total_qty t ON a.demand_wo = t.demand_wo AND a.pn = t.pn AND a.model = t.model AND a.lot = t.lot
        JOIN stockflow_system.prodsys2_delivery_fc_tbl fc ON a.pst = fc.pst
        WHERE a.state = 'WIP-C'
        GROUP BY a.locator, a.pst, a.org, a.demand_wo, a.pn, a.model, a.lot, a.oqc, a.qty, t.total_qty
        ORDER BY remain_del ASC, a.demand_wo, a.pn, a.model, a.lot, a.timestamps;
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // NEW: Query untuk Surat Jalan In-Transit (Belum Received)
    public function getInTransitSJ() {
        $query = "
        SELECT
            no_sj,
            MAX(DATE_FORMAT(timestamps, '%d-%b %H:%i')) as depart_time,
            COUNT(DISTINCT model) as total_model,
            SUM(departure_qty) as total_qty,
            'ON THE WAY' as status
        FROM prodsys2_delivery_departure_temp_tbl
        WHERE flag_del = 'Y' AND flag_received = 'N'
        GROUP BY no_sj
        ORDER BY timestamps ASC
        LIMIT 20
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // NEW: Query untuk Notifikasi Toast (Baru Saja Received)
    public function getRecentReceivals() {
        $query = "
        SELECT
            no_sj,
            MAX(received_at) as received_at,
            MAX(received_image_url) as received_image_url,
            SUM(departure_qty) as total_qty,
            MAX(model) as model_sample -- Ambil satu model sebagai sampel display
        FROM prodsys2_delivery_received_tbl
        GROUP BY no_sj
        ORDER BY received_at DESC
        LIMIT 1
        ";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>