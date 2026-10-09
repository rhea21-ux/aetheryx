<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ÆTHERYX SENTINEL — Intelligence Library
|--------------------------------------------------------------------------
|
| Six explainable, rule-based models. None of this is machine learning —
| every function returns the exact factors that produced its result, and
| every input is a real column from the live schema (equipment, inventory,
| compatibility, maintenance_requests).
|
| Design rule: every function here is PURE. It takes already-fetched data
| (arrays) and returns a result — it never opens a database connection or
| runs a query itself. The sentinel/*.php API endpoints (steps 8-11) do the
| fetching; this file just does the reasoning. That keeps each model
| testable on its own and keeps the "why" logic in one place instead of
| scattered across endpoints.
|
*/

// --------------------------------------------------------------------------
// MODEL 1 — ASSET HEALTH SCORE
// --------------------------------------------------------------------------
//
// Inputs (all real columns):
//   equipment.condition_status  excellent|good|fair|damaged|NULL
//   equipment.status            available|unavailable|maintenance
//   equipment.quantity / available_quantity
//   count of open maintenance_requests for this equipment
//   count of inventory rows with status damaged|lost for this equipment
//
function sentinelAssetHealth(
    array $equipment,
    int $openMaintenanceCount,
    int $damagedOrLostInventoryCount
): array {

    $score = 100;
    $reasons = [];

    /*
     * Condition
     */
    $condition = $equipment['condition_status'] ?? null;

    switch ($condition) {
        case 'fair':
            $score -= 15;
            $reasons[] = ['delta' => -15, 'label' => 'Condition recorded as fair'];
            break;

        case 'damaged':
            $score -= 35;
            $reasons[] = ['delta' => -35, 'label' => 'Condition recorded as damaged'];
            break;

        case 'excellent':
        case 'good':
            // No deduction.
            break;

        default:
            // NULL — not a health judgment, a data-quality flag.
            $score -= 5;
            $reasons[] = ['delta' => -5, 'label' => 'Condition has not been recorded yet'];
            break;
    }

    /*
     * Operational status
     */
    $status = (string) ($equipment['status'] ?? 'available');

    if ($status === 'maintenance') {
        $score -= 30;
        $reasons[] = ['delta' => -30, 'label' => 'Currently under maintenance'];
    } elseif ($status === 'unavailable') {
        $score -= 25;
        $reasons[] = ['delta' => -25, 'label' => 'Currently marked unavailable'];
    }

    /*
     * Availability ratio
     */
    $quantity = (int) ($equipment['quantity'] ?? 0);
    $available = (int) ($equipment['available_quantity'] ?? 0);

    if ($quantity > 0) {
        $ratio = $available / $quantity;

        if ($ratio < 0.30) {
            $score -= 15;
            $reasons[] = [
                'delta' => -15,
                'label' => sprintf(
                    'Availability is low (%d of %d, %d%%)',
                    $available,
                    $quantity,
                    (int) round($ratio * 100)
                )
            ];
        } elseif ($ratio < 0.60) {
            $score -= 10;
            $reasons[] = [
                'delta' => -10,
                'label' => sprintf(
                    'Availability is reduced (%d of %d, %d%%)',
                    $available,
                    $quantity,
                    (int) round($ratio * 100)
                )
            ];
        }
    }

    /*
     * Open maintenance requests (capped at -20)
     */
    if ($openMaintenanceCount > 0) {
        $delta = min($openMaintenanceCount * 10, 20);
        $score -= $delta;
        $reasons[] = [
            'delta' => -$delta,
            'label' => sprintf(
                '%d open maintenance request%s',
                $openMaintenanceCount,
                $openMaintenanceCount === 1 ? '' : 's'
            )
        ];
    }

    /*
     * Damaged / lost inventory instances (capped at -15)
     */
    if ($damagedOrLostInventoryCount > 0) {
        $delta = min($damagedOrLostInventoryCount * 10, 15);
        $score -= $delta;
        $reasons[] = [
            'delta' => -$delta,
            'label' => sprintf(
                '%d unit%s recorded damaged or lost in inventory history',
                $damagedOrLostInventoryCount,
                $damagedOrLostInventoryCount === 1 ? '' : 's'
            )
        ];
    }

    $score = max(0, min(100, $score));

    return [
        'score' => $score,
        'band' => sentinelHealthBand($score),
        'reasons' => $reasons
    ];
}

function sentinelHealthBand(int $score): string
{
    if ($score >= 80) {
        return 'HEALTHY';
    }

    if ($score >= 50) {
        return 'MONITOR';
    }

    if ($score >= 25) {
        return 'ATTENTION';
    }

    return 'CRITICAL';
}

// --------------------------------------------------------------------------
// MODEL 2 — MAINTENANCE PRIORITY ENGINE
// --------------------------------------------------------------------------
//
// Inputs:
//   maintenance_requests.reported_severity   (requester's own self-report)
//   equipment.status                          at time of evaluation
//   age of the request in days
//   count of OTHER open maintenance_requests on the same equipment
//
function sentinelMaintenancePriority(
    string $reportedSeverity,
    string $equipmentStatus,
    int $ageDays,
    int $otherOpenRequestsOnSameEquipment
): array {

    $severityBase = [
        'low' => 1,
        'normal' => 2,
        'high' => 3,
        'critical' => 4
    ];

    $points = $severityBase[$reportedSeverity] ?? 2;
    $factors = [sprintf('Reported severity: %s', ucfirst($reportedSeverity))];

    if ($equipmentStatus !== 'available') {
        $points += 1;
        $factors[] = sprintf('Equipment status is currently "%s"', $equipmentStatus);
    }

    if ($ageDays > 7) {
        $points += 1;
        $factors[] = sprintf('Request has been open for %d days', $ageDays);
    }

    if ($otherOpenRequestsOnSameEquipment >= 2) {
        $points += 1;
        $factors[] = sprintf(
            '%d other open requests exist for this same equipment',
            $otherOpenRequestsOnSameEquipment
        );
    }

    if ($points >= 6) {
        $priority = 'CRITICAL';
    } elseif ($points >= 4) {
        $priority = 'HIGH';
    } elseif ($points === 3) {
        $priority = 'NORMAL';
    } else {
        $priority = 'LOW';
    }

    return [
        'priority' => $priority,
        'points' => $points,
        'factors' => $factors
    ];
}

// --------------------------------------------------------------------------
// MODEL 3 — COMPATIBILITY RISK ANALYZER
// --------------------------------------------------------------------------
//
// Input: $compatibilityRows — the compatibility rows already JOINed to
// resources for ONE piece of equipment, each row shaped as:
//   ['resource_id' => int, 'resource_name' => string,
//    'compatibility_type' => 'compatible'|'incompatible', 'notes' => ?string]
//
// An equipment item with NO rows at all is a data-quality flag, not a
// danger flag — worded that way deliberately so it isn't read as a warning
// about the equipment itself.
//
function sentinelCompatibilityRisk(array $compatibilityRows): array
{
    if (count($compatibilityRows) === 0) {
        return [
            'riskLevel' => 'UNKNOWN',
            'reason' => 'No compatibility data has been recorded for this equipment.',
            'recommendedAction' => 'Add compatibility records so Sentinel can assess risk.',
            'items' => []
        ];
    }

    $incompatible = array_values(array_filter(
        $compatibilityRows,
        static fn(array $row): bool => ($row['compatibility_type'] ?? '') === 'incompatible'
    ));

    if (count($incompatible) === 0) {
        return [
            'riskLevel' => 'NONE',
            'reason' => 'All recorded resource combinations are compatible.',
            'recommendedAction' => 'No action needed.',
            'items' => $compatibilityRows
        ];
    }

    $riskLevel = count($incompatible) >= 2 ? 'HIGH' : 'LOW';

    return [
        'riskLevel' => $riskLevel,
        'reason' => sprintf(
            '%d incompatible resource combination%s recorded.',
            count($incompatible),
            count($incompatible) === 1 ? '' : 's'
        ),
        'recommendedAction' => 'Review flagged resource pairings before assignment.',
        'items' => $incompatible
    ];
}

// --------------------------------------------------------------------------
// MODEL 4 — ASSET LIFECYCLE ENGINE
// --------------------------------------------------------------------------
//
// Recommendation only. Never writes to equipment.status.
//
// $criticalSinceDays: how many consecutive days this asset has evaluated
// as CRITICAL, if that's being tracked by the caller (optional — pass null
// if not available yet; RETIREMENT REVIEW simply becomes unreachable).
//
function sentinelLifecycleState(
    array $equipment,
    int $healthScore,
    int $openMaintenanceCount,
    int $damagedOrLostInventoryCount,
    ?int $criticalSinceDays = null
): array {

    $condition = $equipment['condition_status'] ?? null;
    $status = (string) ($equipment['status'] ?? 'available');

    $isCritical = $condition === 'damaged'
        || $healthScore < 25
        || ($status === 'unavailable' && $openMaintenanceCount >= 2);

    if ($isCritical) {

        if (
            $condition === 'damaged'
            && $criticalSinceDays !== null
            && $criticalSinceDays > 30
            && $damagedOrLostInventoryCount >= 2
        ) {
            return [
                'state' => 'RETIREMENT_REVIEW',
                'reason' => sprintf(
                    'Condition has been damaged and critical for %d days, with %d damaged/lost inventory records. '
                        . 'Sentinel recommends a human review of whether this asset should remain in service.',
                    $criticalSinceDays,
                    $damagedOrLostInventoryCount
                )
            ];
        }

        return [
            'state' => 'CRITICAL',
            'reason' => 'Health score, condition, or status/request combination indicates urgent review is needed.'
        ];
    }

    $isAttention = $condition === 'fair'
        || $healthScore < 50
        || ($status === 'maintenance' && $openMaintenanceCount > 0);

    if ($isAttention) {
        return [
            'state' => 'ATTENTION',
            'reason' => 'Multiple operational signals indicate this asset needs review.'
        ];
    }

    $isHealthy = in_array($condition, ['excellent', 'good'], true)
        && $status === 'available'
        && $healthScore >= 80;

    if ($isHealthy) {
        return [
            'state' => 'HEALTHY',
            'reason' => 'Good condition, available, no significant warning signals.'
        ];
    }

    return [
        'state' => 'MONITOR',
        'reason' => 'Some warning indicators exist but nothing urgent yet.'
    ];
}

// --------------------------------------------------------------------------
// MODEL 5 — INVENTORY PRESSURE ANALYZER
// --------------------------------------------------------------------------
//
// Pure arithmetic on equipment.quantity / available_quantity.
//
function sentinelInventoryPressure(array $equipment): array
{
    $quantity = (int) ($equipment['quantity'] ?? 0);
    $available = (int) ($equipment['available_quantity'] ?? 0);

    if ($quantity <= 0) {
        return [
            'pressure' => 'UNKNOWN',
            'availabilityPercent' => 0,
            'reason' => 'No quantity recorded for this equipment.'
        ];
    }

    $percent = (int) round(($available / $quantity) * 100);

    if ($percent <= 0) {
        $pressure = 'CRITICAL';
    } elseif ($percent <= 25) {
        $pressure = 'HIGH';
    } elseif ($percent <= 50) {
        $pressure = 'MEDIUM';
    } else {
        $pressure = 'LOW';
    }

    return [
        'pressure' => $pressure,
        'availabilityPercent' => $percent,
        'reason' => sprintf(
            'Availability is %d%% (%d of %d).',
            $percent,
            $available,
            $quantity
        )
    ];
}

/*
 * Category-level rollup. $equipmentList is an array of equipment rows
 * (each with category_id, quantity, available_quantity); returns pressure
 * grouped by category_id using the same bands as the per-item function.
 */
function sentinelInventoryPressureByCategory(array $equipmentList): array
{
    $totals = [];

    foreach ($equipmentList as $item) {
        $categoryId = $item['category_id'] ?? 'uncategorized';

        if (!isset($totals[$categoryId])) {
            $totals[$categoryId] = ['quantity' => 0, 'available' => 0];
        }

        $totals[$categoryId]['quantity'] += (int) ($item['quantity'] ?? 0);
        $totals[$categoryId]['available'] += (int) ($item['available_quantity'] ?? 0);
    }

    $result = [];

    foreach ($totals as $categoryId => $sums) {
        $result[$categoryId] = sentinelInventoryPressure([
            'quantity' => $sums['quantity'],
            'available_quantity' => $sums['available']
        ]);
    }

    return $result;
}

// --------------------------------------------------------------------------
// MODEL 6 — SENTINEL RECOMMENDATION ENGINE
// --------------------------------------------------------------------------
//
// Not an independent calculation — reads the outputs of models 1, 4, and 5
// for one asset and surfaces the single most important next action, with
// the strongest-weighted reason attached. Explainable by construction: it
// only ever repeats a reason one of the other models already produced.
//
// --------------------------------------------------------------------------
// Maintenance workflow helpers
// --------------------------------------------------------------------------
//
// Unlike the models above, these two DO write to the database — kept here
// because every maintenance/*.php endpoint needs both, and duplicating the
// insert pattern six times is the kind of thing section 7 of the Sentinel
// spec (shared helpers over copy-paste) is meant to avoid.
//
function sentinelLogActivity(
    PDO $pdo,
    ?int $userId,
    string $action,
    string $description,
    string $entityType,
    int $entityId
): void {

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address)
        VALUES (:user_id, :action, :description, :entity_type, :entity_id, :ip_address)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':description' => $description,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

function sentinelNotify(
    PDO $pdo,
    int $userId,
    string $title,
    string $message,
    string $type = 'info'
): void {

    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (:user_id, :title, :message, :type)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':message' => $message,
        ':type' => $type
    ]);
}

function sentinelRecommendation(
    array $health,
    array $lifecycle,
    array $pressure
): array {

    // Lifecycle state drives the headline action; health's largest
    // deduction supplies the "why."
    $topReason = null;

    if (count($health['reasons']) > 0) {
        $topReason = array_reduce(
            $health['reasons'],
            static fn($carry, $item) => ($carry === null || $item['delta'] < $carry['delta']) ? $item : $carry
        );
    }

    $actionsByState = [
        'RETIREMENT_REVIEW' => 'Review Asset Details → Consider retirement review',
        'CRITICAL' => 'Open Asset Details → Review Status → Consider maintenance request',
        'ATTENTION' => 'Open Asset Details → Review condition and open requests',
        'MONITOR' => 'No immediate action — continue monitoring',
        'HEALTHY' => 'No action needed'
    ];

    $action = $actionsByState[$lifecycle['state']] ?? 'Open Asset Details → Review';

    $why = [$lifecycle['reason']];

    if ($topReason !== null) {
        $why[] = $topReason['label'];
    }

    if ($pressure['pressure'] === 'HIGH' || $pressure['pressure'] === 'CRITICAL') {
        $why[] = $pressure['reason'];
    }

    return [
        'action' => $action,
        'why' => $why
    ];
}

// --------------------------------------------------------------------------
// WORKFLOW — maintenance_requests status transitions
// --------------------------------------------------------------------------
//
// Not a scoring model — a shared rule set so update.php, assign.php, and
// resolve.php all enforce the same valid transitions instead of each
// defining its own copy.
//
function sentinelValidMaintenanceTransitions(): array
{
    return [
        'open' => ['assigned', 'in_progress', 'cancelled'],
        'assigned' => ['in_progress', 'cancelled', 'resolved'],
        'in_progress' => ['resolved', 'cancelled'],
        'resolved' => [],
        'cancelled' => []
    ];
}

function sentinelIsValidMaintenanceTransition(string $from, string $to): bool
{
    $transitions = sentinelValidMaintenanceTransitions();

    return in_array($to, $transitions[$from] ?? [], true);
}
