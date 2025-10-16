<?php
include 'db_connect.php';

// Ensure PHP uses Philippine timezone
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_now'])) {
    $conn->begin_transaction();

    try {
        // Get the latest group_number for today from all tables
        $result = $conn->query("
            SELECT MAX(group_number) AS last_group FROM (
                SELECT MAX(group_number) AS group_number FROM released_summary WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM payroll WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM immediate_material WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM reimbursements WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM misc_expenses WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM office_expenses WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM utilities_expenses WHERE released_date = '$today'
                UNION ALL
                SELECT MAX(group_number) FROM sub_contracts WHERE released_date = '$today'
            ) AS all_today_groups
        ");
        $row = $result->fetch_assoc();
        $lastGroup = $row['last_group'] ?? 0;
        $newGroup = $lastGroup + 1; // start at 1 if no releases today

        // 1. Insert approved rows into released_summary with group_number
        $insertSQL = "INSERT INTO released_summary 
            (po_number, item_description, qty, unit, unit_price, total_price, supplier_name, address, contact_number, contact_person, 
             ship_project_name, ship_address, ship_contact_number, ship_contact_person, created_at, date, particulars, po_num, status, mrf_id, released_date, group_number)
            SELECT 
             po_number, item_description, qty, unit, unit_price, total_price, supplier_name, address, contact_number, contact_person, 
             ship_project_name, ship_address, ship_contact_number, ship_contact_person, created_at, date, particulars, po_num, status, mrf_id,
             '$today' AS released_date,
             $newGroup AS group_number
            FROM summary_approved
            WHERE status = 'Approved'";

        if (!$conn->query($insertSQL)) {
            throw new Exception("Insert failed: " . $conn->error);
        }

        // 2. Get distinct mrf_ids from summary_approved
        $result = $conn->query("SELECT DISTINCT mrf_id FROM summary_approved WHERE mrf_id IS NOT NULL AND mrf_id != ''");
        if ($result) {
            $mrf_ids = [];
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['mrf_id'])) {
                    $mrf_ids[] = (int)$row['mrf_id'];
                }
            }
            if (!empty($mrf_ids)) {
                $ids_str = implode(',', $mrf_ids);
                $updateSQL = "UPDATE requested_mrf SET status = 'released' WHERE id IN ($ids_str)";
                if (!$conn->query($updateSQL)) {
                    throw new Exception("Failed to update requested_mrf statuses: " . $conn->error);
                }
            }            
        } else {
            throw new Exception("Failed to fetch mrf_ids: " . $conn->error);
        }

        // 3. Update other expense-related tables with group_number
        $tables = [
            'payroll',
            'immediate_material',
            'reimbursements',
            'misc_expenses',
            'office_expenses',
            'utilities_expenses',
            'sub_contracts'
        ];

        foreach ($tables as $table) {
            $updateSQL = "UPDATE $table SET status = 'released', released_date = '$today', group_number = $newGroup WHERE status = 'Approved'";
            if (!$conn->query($updateSQL)) {
                throw new Exception("Failed to update $table statuses: " . $conn->error);
            }
        }

        // 4. Delete approved entries from summary_approved
        $deleteSQL = "DELETE FROM summary_approved WHERE status = 'Approved'";
        if (!$conn->query($deleteSQL)) {
            throw new Exception("Delete failed: " . $conn->error);
        }

        $conn->commit();
        header("Location: summary_approved.php?release=success");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        echo "Transaction failed: " . $e->getMessage();
    }
} else {
    header("Location: summary_approved.php");
    exit;
}
?>
