<?php
declare(strict_types=1);

require_once __DIR__ . '/../../middleware/role-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

requireMarketplaceRole();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405, ['code' => 'METHOD_NOT_ALLOWED']);
}

$userId = getAuthenticatedUserId();
$role = getAuthenticatedRole();
$data = json_decode((string) file_get_contents('php://input'), true);
$inventoryId = is_array($data) ? filter_var($data['inventory_id'] ?? null, FILTER_VALIDATE_INT) : false;
$salePriceCents = is_array($data) ? filter_var($data['sale_price_cents'] ?? null, FILTER_VALIDATE_INT) : false;

if (!$userId || !in_array($role, ['hero', 'enemy'], true) || !$inventoryId || $inventoryId < 1) {
    respond(false, 'A valid assigned inventory record is required.', 422, ['code' => 'INVALID_INVENTORY_ID']);
}
if ($salePriceCents === false || $salePriceCents < 1) {
    respond(false, 'Choose a valid resale price.', 422, ['code' => 'INVALID_SALE_PRICE']);
}

try {
    $pdo->beginTransaction();

    $itemStmt = $pdo->prepare(
        'SELECT i.id, i.equipment_id, i.quantity, i.status, i.purchase_price, e.price,
                e.name AS equipment_name, e.quantity AS stock_quantity,
                e.available_quantity
         FROM inventory i
         INNER JOIN equipment e ON e.id = i.equipment_id
         WHERE i.id = :id AND i.user_id = :user_id
         FOR UPDATE'
    );
    $itemStmt->execute([':id' => $inventoryId, ':user_id' => $userId]);
    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        $pdo->rollBack();
        respond(false, 'Inventory record not found.', 404, ['code' => 'NOT_FOUND']);
    }
    if ($item['status'] !== 'assigned') {
        $pdo->rollBack();
        respond(false, 'Only assigned equipment can be sold.', 409, ['code' => 'NOT_SELLABLE']);
    }

    $listed = $pdo->prepare("SELECT id FROM resale_listings WHERE inventory_id = :id AND status = 'active' LIMIT 1");
    $listed->execute([':id' => $inventoryId]);
    if ($listed->fetchColumn()) {
        $pdo->rollBack();
        respond(false, 'Withdraw this active resale listing before selling the item back.', 409, ['code' => 'ITEM_LISTED_FOR_RESALE']);
    }

    $referencePrice = (float) $item['purchase_price'] > 0
        ? (float) $item['purchase_price']
        : (float) $item['price'];
    $quantity = (int) $item['quantity'];
    if (!is_finite($referencePrice) || $referencePrice <= 0 || $quantity < 1) {
        $pdo->rollBack();
        respond(false, 'This equipment does not have a valid reference price.', 409, ['code' => 'INVALID_PRICE']);
    }

    $referencePriceCents = (int) round($referencePrice * 100, 0, PHP_ROUND_HALF_UP);
    $minimumSaleCents = max(1, intdiv($referencePriceCents, 2));
    $maximumSaleCents = intdiv($referencePriceCents * 95, 100);
    if ($maximumSaleCents < $minimumSaleCents) {
        $pdo->rollBack();
        respond(false, 'This equipment price is too low to calculate a valid resale range.', 409, ['code' => 'INVALID_PRICE']);
    }
    if ($salePriceCents < $minimumSaleCents || $salePriceCents > $maximumSaleCents) {
        $pdo->rollBack();
        respond(false, 'The resale price must be between 50% and 95% of the reference price.', 422, [
            'code' => 'PRICE_OUT_OF_RANGE',
            'minimumSalePrice' => $minimumSaleCents / 100,
            'maximumSalePrice' => $maximumSaleCents / 100
        ]);
    }
    $unitPrice = $salePriceCents / 100;

    if ((int) $item['available_quantity'] + $quantity > (int) $item['stock_quantity']) {
        $pdo->rollBack();
        respond(false, 'The equipment stock count needs admin review before this sale can be completed.', 409, ['code' => 'STOCK_MISMATCH']);
    }

    $sellStmt = $pdo->prepare(
        'UPDATE inventory
         SET status = "sold", sale_price = :sale_price, sold_at = CURRENT_TIMESTAMP
         WHERE id = :id AND user_id = :user_id AND status = "assigned"'
    );
    $sellStmt->execute([':sale_price' => $unitPrice, ':id' => $inventoryId, ':user_id' => $userId]);
    if ($sellStmt->rowCount() !== 1) {
        $pdo->rollBack();
        respond(false, 'This equipment has already been sold or changed.', 409, ['code' => 'NOT_SELLABLE']);
    }

    $pdo->prepare(
        'UPDATE equipment
         SET available_quantity = available_quantity + :quantity,
             status = CASE WHEN status = "unavailable" THEN "available" ELSE status END
         WHERE id = :equipment_id'
    )->execute([':quantity' => $quantity, ':equipment_id' => (int) $item['equipment_id']]);

    $totalPrice = $unitPrice * $quantity;
    $pdo->commit();
    $message = sprintf('Your %s was sold back for PHP %s.', $item['equipment_name'], number_format($totalPrice, 2, '.', ','));
    createNotification($pdo, (int) $userId, 'Equipment sold', $message, 'success');
    notifyAdminsOfActivity($pdo, (int) $userId, 'Equipment sold back', $message, 'info');
    respond(true, 'Equipment sold successfully.', 200, [
        'inventoryId' => (int) $inventoryId,
        'quantity' => $quantity,
        'unitPrice' => $unitPrice,
        'totalPrice' => $totalPrice
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('AETHERYX inventory sell error: ' . $e->getMessage());
    respond(false, 'Unable to sell equipment.', 500, ['code' => 'SELL_FAILED']);
}
