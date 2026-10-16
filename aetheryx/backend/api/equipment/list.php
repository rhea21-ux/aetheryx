<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| ÆTHERYX Equipment List API
|--------------------------------------------------------------------------
| Access:
|   hero    → common + hero
|   enemy   → common + enemy
|   admin   → common equipment only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

try {

    // User must be logged in.
    requireAuth();

    $role = getAuthenticatedRole();

    if (!in_array($role, ['admin', 'hero', 'enemy'], true)) {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid account role.'
        ]);

        exit;
    }

    /*
     * Determine which equipment the current role can access.
     */
    if ($role === 'hero') {

        $allowedRoles = ['common', 'hero'];

    } elseif ($role === 'enemy') {

        $allowedRoles = ['common', 'enemy'];

    } else {

        // Admin can inspect the complete equipment catalog.
        $allowedRoles = ['common', 'hero', 'enemy'];
    }

    /*
     * Build placeholders safely for the IN clause.
     */
    $placeholders = implode(
        ', ',
        array_fill(0, count($allowedRoles), '?')
    );

    $characterRole = strtolower(trim((string)($_GET['character_role'] ?? '')));
    $allowedCharacterRoles = ['mage', 'fighter', 'marksman', 'tank', 'support'];
    if ($characterRole !== '' && $characterRole !== 'multiple'
        && !in_array($characterRole, $allowedCharacterRoles, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid character role filter.']);
        exit;
    }

    $roleSql = $characterRole === '' ? '' : ($characterRole === 'multiple'
        ? ' AND (SELECT COUNT(*) FROM equipment_character_roles ecr WHERE ecr.equipment_id=e.id) > 1'
        : ' AND EXISTS (SELECT 1 FROM equipment_character_roles ecr WHERE ecr.equipment_id=e.id AND ecr.character_role = ?)');
    $sql = "
        SELECT
            e.id,
            e.category_id,
            e.access_role,
            e.is_black_market,
            e.name,
            e.item_type,
            e.description,
            e.lore,
            e.rarity,
            e.fictional_effect,
            e.brand,
            e.quantity,
            e.available_quantity,
            e.price,
            e.status,
            e.image_url,
            e.created_at,
            e.updated_at,
            c.name AS category_name,
            (SELECT GROUP_CONCAT(ecr.character_role ORDER BY ecr.character_role SEPARATOR ',')
             FROM equipment_character_roles ecr WHERE ecr.equipment_id=e.id) AS compatible_roles
        FROM equipment e
        LEFT JOIN categories c
            ON c.id = e.category_id
        WHERE e.access_role IN ($placeholders)
          $roleSql
        ORDER BY e.id ASC
    ";

    $stmt = $pdo->prepare($sql);
    $params = $allowedRoles;
    if ($characterRole !== '' && $characterRole !== 'multiple') $params[] = $characterRole;
    $stmt->execute($params);

    $equipment = $stmt->fetchAll();

    /*
     * Format database values for the frontend.
     */
    $items = array_map(
        static function (array $item): array {

            $accessRole = (string) $item['access_role'];

            $displayAccess = match ($accessRole) {
                'hero' => 'Hero Only',
                'enemy' => 'Villain Only',
                default => 'Common'
            };

            return [
                'id' => (int) $item['id'],
                'categoryId' => $item['category_id'] !== null
                    ? (int) $item['category_id']
                    : null,

                'category' => $item['category_name'] ?? 'Uncategorized',
                'compatibleRoles' => empty($item['compatible_roles']) ? [] : explode(',', $item['compatible_roles']),

                'accessRole' => $accessRole,
                'displayAccess' => $displayAccess,
                'isBlackMarket' => (bool) $item['is_black_market'],

                'name' => $item['name'],
                'itemType' => $item['item_type'],
                'description' => $item['description'],
                'lore' => $item['lore'],
                'rarity' => $item['rarity'],
                'fictionalEffect' => $item['fictional_effect'],
                'brand' => $item['brand'],

                'quantity' => (int) $item['quantity'],
                'availableQuantity' => (int) $item['available_quantity'],
                'price' => (float) $item['price'],

                'status' => $item['status'],

                'imageUrl' => $item['image_url'],

                'createdAt' => $item['created_at'],
                'updatedAt' => $item['updated_at']
            ];
        },
        $equipment
    );

    echo json_encode([
        'success' => true,
        'message' => 'Equipment retrieved successfully.',
        'role' => $role,
        'count' => count($items),
        'equipment' => $items
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve equipment.'
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}
