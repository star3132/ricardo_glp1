<?php
// ============================================================
// AJAX API endpoint
// Accepts JSON POST, returns JSON
// ============================================================
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON body
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$action = $body['action'] ?? '';

try {
    switch ($action) {

        // ---- update_cell ----------------------------------------
        case 'update_cell': {
            $boxId     = filter_var($body['box_id']     ?? null, FILTER_VALIDATE_INT);
            $row       = filter_var($body['row']        ?? null, FILTER_VALIDATE_INT);
            $col       = filter_var($body['col']        ?? null, FILTER_VALIDATE_INT);
            $medId     = filter_var($body['medicine_id'] ?? 0,   FILTER_VALIDATE_INT);
            $qty       = filter_var($body['quantity']   ?? 0,    FILTER_VALIDATE_INT);

            if ($boxId === false || $row === false || $col === false) {
                throw new InvalidArgumentException('Missing or invalid parameters');
            }

            // Validate box exists
            $boxStmt = db()->prepare('SELECT rows, cols FROM boxes WHERE id = ? LIMIT 1');
            $boxStmt->execute([$boxId]);
            $box = $boxStmt->fetch();
            if (!$box) throw new RuntimeException('Box not found');

            // Bounds check
            if ($row < 0 || $row >= $box['rows'] || $col < 0 || $col >= $box['cols']) {
                throw new InvalidArgumentException('Cell out of bounds');
            }

            $medId = ($medId && $medId > 0) ? $medId : null;
            $qty   = max(0, (int)$qty);

            // If medicine is null, set quantity to 0
            if ($medId === null) $qty = 0;

            // Validate medicine exists if provided
            if ($medId !== null) {
                $medCheck = db()->prepare('SELECT id, name, color, unit FROM medicines WHERE id = ? LIMIT 1');
                $medCheck->execute([$medId]);
                $med = $medCheck->fetch();
                if (!$med) throw new RuntimeException('Medicine not found');
            }

            // Enforce max_quantity if cell already exists
            $existingStmt = db()->prepare(
                'SELECT max_quantity FROM cells WHERE box_id = ? AND row_idx = ? AND col_idx = ? LIMIT 1'
            );
            $existingStmt->execute([$boxId, $row, $col]);
            $existing = $existingStmt->fetch();
            $maxQty = $existing ? (int)$existing['max_quantity'] : 10;
            $qty = min($qty, $maxQty);

            // Upsert cell
            $upsert = db()->prepare(
                'INSERT INTO cells (box_id, row_idx, col_idx, medicine_id, quantity)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE medicine_id = VALUES(medicine_id), quantity = VALUES(quantity)'
            );
            $upsert->execute([$boxId, $row, $col, $medId, $qty]);

            // Return updated cell data
            $returnMed = null;
            if ($medId !== null && isset($med)) {
                $returnMed = $med;
            }

            echo json_encode([
                'ok'       => true,
                'quantity' => $qty,
                'max'      => $maxQty,
                'medicine' => $returnMed,
            ]);
            break;
        }

        // ---- get_cell -------------------------------------------
        case 'get_cell': {
            $boxId = filter_var($body['box_id'] ?? null, FILTER_VALIDATE_INT);
            $row   = filter_var($body['row']    ?? null, FILTER_VALIDATE_INT);
            $col   = filter_var($body['col']    ?? null, FILTER_VALIDATE_INT);

            if ($boxId === false || $row === false || $col === false) {
                throw new InvalidArgumentException('Missing or invalid parameters');
            }

            $stmt = db()->prepare(
                'SELECT c.quantity, c.max_quantity, c.medicine_id,
                        m.name AS med_name, m.color AS med_color, m.unit AS med_unit
                 FROM cells c
                 LEFT JOIN medicines m ON m.id = c.medicine_id
                 WHERE c.box_id = ? AND c.row_idx = ? AND c.col_idx = ?
                 LIMIT 1'
            );
            $stmt->execute([$boxId, $row, $col]);
            $cell = $stmt->fetch();

            echo json_encode(['ok' => true, 'cell' => $cell ?: null]);
            break;
        }

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    error_log('API PDOException: ' . $e->getMessage());
}
