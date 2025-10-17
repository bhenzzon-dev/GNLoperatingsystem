<?php
// update_record.php
header('Content-Type: application/json');
require 'db_connect.php'; // ⚠️ make sure $conn = new mysqli(...) is defined there

// Validate required parameters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (!isset($_GET['id']) || !isset($_GET['type'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    $id = intval($_GET['id']);
    $type = $_GET['type'];

    switch ($type) {
        case 'immediate_material':
            $sql = "SELECT im.id, p.project_name, im.particulars, im.amount, im.category
                    FROM immediate_material im
                    INNER JOIN projects p ON im.project_id = p.id
                    WHERE im.id = ?";
            break;

        case 'payroll':
            $sql = "SELECT pr.id, p.project_name, pr.particulars, pr.category, pr.amount
                    FROM payroll pr
                    INNER JOIN projects p ON pr.project_id = p.id
                    WHERE pr.id = ?";
            break;

        case 'reimbursements':
            $sql = "SELECT r.id, p.project_name, r.particulars, r.employee_name, r.amount
                    FROM reimbursements r
                    INNER JOIN projects p ON r.project_id = p.id
                    WHERE r.id = ?";
            break;

        case 'misc_expenses':
            $sql = "SELECT m.id, p.project_name, m.particulars, m.amount, m.supplier_name
                    FROM misc_expenses m
                    INNER JOIN projects p ON m.project_id = p.id
                    WHERE m.id = ?";
            break;

        case 'office_expenses':
            $sql = "SELECT oe.id, oe.particulars, oe.amount, oe.supplier_name
                    FROM office_expenses oe
                    WHERE oe.id = ?";
            break;

        case 'utilities_expenses':
            $sql = "SELECT ue.id, p.project_name, ue.utility_type, ue.amount, ue.account_number, ue.billing_period
                    FROM utilities_expenses ue
                    INNER JOIN projects p ON ue.project_id = p.id
                    WHERE ue.id = ?";
            break;

        case 'sub_contracts':
            $sql = "SELECT sc.id, p.project_name, sc.particular, sc.tcp, sc.category, sc.supplier_name
                    FROM sub_contracts sc
                    INNER JOIN projects p ON sc.project_id = p.id
                    WHERE sc.id = ?";
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    echo json_encode($result ?: []);
    exit;
}

// ----------------------------
// Handle UPDATE (POST request)
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id']) || empty($data['type'])) {
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        exit;
    }

    $id = intval($data['id']);
    $type = $data['type'];

    switch ($type) {
        case 'immediate_material':
            $sql = "UPDATE immediate_material 
                    SET particulars = ?, category = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $data['particulars'], $data['category'], $data['amount'], $id);
            break;

        case 'payroll':
            $sql = "UPDATE payroll 
                    SET particulars = ?, category = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $data['particulars'], $data['category'], $data['amount'], $id);
            break;

        case 'reimbursements':
            $sql = "UPDATE reimbursements 
                    SET particulars = ?, employee_name = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $data['particulars'], $data['employee_name'], $data['amount'], $id);
            break;

        case 'misc_expenses':
            $sql = "UPDATE misc_expenses 
                    SET particulars = ?, supplier_name = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $data['particulars'], $data['supplier_name'], $data['amount'], $id);
            break;

        case 'office_expenses':
            $sql = "UPDATE office_expenses 
                    SET particulars = ?, supplier_name = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdi", $data['particulars'], $data['supplier_name'], $data['amount'], $id);
            break;

        case 'utilities_expenses':
            $sql = "UPDATE utilities_expenses 
                    SET utility_type = ?, billing_period = ?, account_number = ?, amount = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdi", $data['utility_type'], $data['billing_period'], $data['account_number'], $data['amount'], $id);
            break;

        case 'sub_contracts':
            $sql = "UPDATE sub_contracts 
                    SET particular = ?, category = ?, supplier_name = ?, tcp = ?
                    WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssdi", $data['particular'], $data['category'], $data['supplier_name'], $data['tcp'], $id);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid type']);
            exit;
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }

    $stmt->close();
    exit;
}

// ----------------------------
// Fallback for invalid methods
// ----------------------------
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
?>
