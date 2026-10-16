<?php
/**
 * ÆTHERYX — Auto-seed compatibility recommendations for every hero & villain user.
 *
 * Logic:
 *  - Heroes get pairings from  {common} × {hero}  equipment
 *  - Villains get pairings from {common} × {enemy} equipment
 *  - Each user gets 3–5 unique, varied recommendations (compatible + situational)
 *  - No duplicates (respects the uq_user_equipment_compatibility_pair unique key)
 *  - Skips pairs that already exist for that user
 *
 * Run once from the aetheryx project root:
 *   C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe database/seed-compatibility.php
 */

declare(strict_types=1);
require_once __DIR__ . '/../backend/config/database.php';

/* ─── helpers ─────────────────────────────────────────────────────────────── */

function insertPair(
    PDO    $pdo,
    int    $userId,
    int    $eqA,
    int    $eqB,
    string $type,
    string $notes,
    string $situations,
    array  $roles
): bool {
    // Always store with lower id first (matches the unique key convention)
    if ($eqA > $eqB) { [$eqA, $eqB] = [$eqB, $eqA]; }

    // Check duplicate
    $dup = $pdo->prepare(
        'SELECT id FROM equipment_resource_compatibility
         WHERE assigned_user_id = :uid AND equipment_id = :a AND compatible_equipment_id = :b
         LIMIT 1'
    );
    $dup->execute([':uid' => $userId, ':a' => $eqA, ':b' => $eqB]);
    if ($dup->fetchColumn()) {
        echo "  SKIP   user=$userId pair=($eqA,$eqB) — already exists\n";
        return false;
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare(
            'INSERT INTO equipment_resource_compatibility
             (assigned_user_id, equipment_id, compatible_equipment_id, compatibility_type, notes, situations)
             VALUES (:uid, :a, :b, :type, :notes, :situations)'
        );
        $ins->execute([
            ':uid'        => $userId,
            ':a'          => $eqA,
            ':b'          => $eqB,
            ':type'       => $type,
            ':notes'      => $notes,
            ':situations' => $situations !== '' ? $situations : null,
        ]);
        $compatId = (int) $pdo->lastInsertId();

        $roleIns = $pdo->prepare(
            'INSERT INTO equipment_compatibility_roles (compatibility_id, compatible_role)
             VALUES (:cid, :role)'
        );
        foreach (array_unique($roles) as $role) {
            $roleIns->execute([':cid' => $compatId, ':role' => $role]);
        }
        $pdo->commit();
        echo "  INSERT user=$userId pair=($eqA,$eqB) type=$type roles=" . implode(',', $roles) . "\n";
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        echo "  ERROR  user=$userId pair=($eqA,$eqB): " . $e->getMessage() . "\n";
        return false;
    }
}

/* ─── fetch live data ──────────────────────────────────────────────────────── */

$users = $pdo->query(
    "SELECT id, handle, role FROM users
     WHERE status = 'active' AND role IN ('hero', 'enemy')
     ORDER BY role, id"
)->fetchAll(PDO::FETCH_ASSOC);

$allEquipment = $pdo->query(
    "SELECT id, name, access_role FROM equipment WHERE status = 'available' ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$common  = array_values(array_filter($allEquipment, fn($e) => $e['access_role'] === 'common'));
$hero    = array_values(array_filter($allEquipment, fn($e) => $e['access_role'] === 'hero'));
$villain = array_values(array_filter($allEquipment, fn($e) => $e['access_role'] === 'enemy'));

/* ─── recommendation templates ─────────────────────────────────────────────── */

// Hero pairings: common × hero
$heroPairs = [
    // [common_idx, hero_idx, type, roles, notes, situations]
    [0, 0, 'compatible', ['fighter','tank'],
        'The Reinforced Field Vest pairs seamlessly with the Hero Shield Module. Together they provide layered defence — the vest absorbs impact while the shield module redirects energy-based attacks. Recommended for front-line operations.',
        ''],
    [1, 1, 'compatible', ['mage','marksman'],
        'Multi-Tool Kit extends the utility of the Grapple Launcher. Technicians can use the kit to recalibrate launch tension on the fly, dramatically increasing traversal range in vertical environments.',
        ''],
    [2, 2, 'situational', ['mage','support'],
        'Secure Radio combined with the Dawnforged Blade creates a coordinated strike-and-signal setup. The blade draws enemy attention while the radio relays real-time position data to allied support.',
        'Best used during multi-squad operations where the Hero is the designated flanker.'],
    [3, 3, 'compatible', ['mage'],
        'Jet Boots and the Aetherweave Staff synergise for aerial spellcasting. The boots grant elevation, while the staff\'s arc radius benefits from height advantage, extending hit zones by up to 40%.',
        ''],
    [4, 4, 'compatible', ['tank','fighter'],
        'Concordance Sigil amplifies the passive regeneration on Guardian\'s Bastion. The sigil\'s resonance field keeps the bastion energised longer, enabling sustained shielding without cooldown gaps.',
        ''],
    [0, 5, 'compatible', ['marksman'],
        'Reinforced Field Vest and Skytrace Longbow form a mobile sniper loadout. The vest keeps the marksman in action through minor skirmishes while the bow handles long-range targeting from cover.',
        ''],
    [1, 6, 'compatible', ['support'],
        'Multi-Tool Kit is the ideal companion for the Lifebloom Charm. Support units can use the kit to recharge the charm\'s botanical cells mid-mission, extending healing uptime without returning to base.',
        ''],
    [2, 0, 'situational', ['fighter','tank'],
        'Secure Radio with Hero Shield Module enables a defensive communication post. The combination lets the Hero coordinate retreats while maintaining a hard defensive perimeter.',
        'Use when acting as rear-guard during extractions or objective holds.'],
    [3, 2, 'compatible', ['mage'],
        'Jet Boots and Dawnforged Blade enable a high-speed aerial strike combo. Boost into the air, close distance rapidly, then land a momentum-boosted strike for significantly increased damage.',
        ''],
    [4, 5, 'situational', ['marksman'],
        'Concordance Sigil and Skytrace Longbow create a sustained sniper platform. The sigil\'s focus enhancement stacks with the bow\'s aim assist, reducing shot deviation on consecutive rapid fires.',
        'Effective against fast-moving targets or drone swarms during defence missions.'],
];

// Villain pairings: common × enemy
$villainPairs = [
    [0, 0, 'compatible', ['fighter','tank'],
        'Reinforced Field Vest worn under the Dreadplate Armor creates a dual-layer protection system. The vest\'s shock-absorbing lining complements the plate\'s rigid exterior, filling gaps in blunt-force coverage.',
        ''],
    [1, 1, 'compatible', ['mage'],
        'Multi-Tool Kit is essential for calibrating the Umbral Grimoire\'s binding array. Without regular maintenance only possible with the kit, the grimoire\'s summoning seals degrade over prolonged use.',
        ''],
    [2, 2, 'compatible', ['mage','support'],
        'Secure Radio integrated with Hexbound Relic enables encrypted cursed-channel communications. Enemy intercept probability drops to near zero, making this the preferred stealth coordination setup.',
        ''],
    [3, 3, 'situational', ['marksman'],
        'Jet Boots combined with Nightpiercer Crossbow create a hit-and-run aerial assault pattern. Launch overhead, fire a volley, then reposition before targets can respond.',
        'Best employed against stationary or slow-moving hero clusters.'],
    [4, 4, 'compatible', ['fighter'],
        'Concordance Sigil channels excess dark energy generated by Voidreaver Scythe back into the wielder. This passive recovery prevents the scythe\'s energy drain from becoming a liability in extended fights.',
        ''],
    [0, 5, 'compatible', ['fighter'],
        'Reinforced Field Vest paired with the Obsidian Phase Cloak creates a deceptive tank-assassin profile. The vest sustains health in brief exposure windows while the cloak handles re-entry to stealth.',
        ''],
    [1, 6, 'compatible', ['mage','marksman'],
        'Multi-Tool Kit keeps Riftlock Breacher charges cycling efficiently. The breacher\'s rifting mechanism requires periodic recalibration; the kit cuts downtime between uses by 60%.',
        ''],
    [2, 7, 'situational', ['mage'],
        'Secure Radio and Nightfall Signal Scrambler in tandem create a communication dead zone. The scrambler silences enemy comms while the radio keeps villain-side channels operational on a separate band.',
        'Optimal during ambushes or siege operations where hero coordination must be disrupted.'],
    [3, 0, 'compatible', ['tank'],
        'Jet Boots and Villain Toolkit expand field mobility for heavy villains. The toolkit\'s gear allows rapid modification of boot thrust, adapting the thrust output for terrain type on the fly.',
        ''],
    [4, 4, 'situational', ['fighter'],
        'Concordance Sigil worn alongside Dreadplate Armor enables a slow-burn attrition strategy. The sigil gradually repairs micro-fractures in the armour plating between engagement cycles.',
        'Most effective in prolonged siege scenarios where the villain acts as the primary distraction.'],
];

/* ─── seed per user ──────────────────────────────────────────────────────────── */

$totalInserted = 0;

foreach ($users as $user) {
    $uid    = (int) $user['id'];
    $handle = $user['handle'];
    $role   = $user['role'];

    echo "\n── User #$uid @$handle ($role) ──\n";

    if ($role === 'hero') {
        // Give each hero a distinct rotating set of 4 pairs from heroPairs
        $pairCount  = count($heroPairs);
        $startIndex = ($uid % $pairCount); // rotate start per user so each gets different pairs first
        $assigned   = 0;

        for ($i = 0; $i < $pairCount && $assigned < 4; $i++) {
            $p   = $heroPairs[($startIndex + $i) % $pairCount];
            $cId = isset($common[$p[0]]) ? (int) $common[$p[0]]['id'] : null;
            $hId = isset($hero[$p[1]])   ? (int) $hero[$p[1]]['id']   : null;
            if (!$cId || !$hId) continue;

            $ok = insertPair($pdo, $uid, $cId, $hId, $p[2], $p[4], $p[5] ?? '', $p[3]);
            if ($ok) { $assigned++; $totalInserted++; }
        }
    } else {
        // Villain
        $pairCount  = count($villainPairs);
        $startIndex = ($uid % $pairCount);
        $assigned   = 0;

        for ($i = 0; $i < $pairCount && $assigned < 4; $i++) {
            $p   = $villainPairs[($startIndex + $i) % $pairCount];
            $cId = isset($common[$p[0]])  ? (int) $common[$p[0]]['id']  : null;
            $vId = isset($villain[$p[1]]) ? (int) $villain[$p[1]]['id'] : null;
            if (!$cId || !$vId) continue;

            $ok = insertPair($pdo, $uid, $cId, $vId, $p[2], $p[4], $p[5] ?? '', $p[3]);
            if ($ok) { $assigned++; $totalInserted++; }
        }
    }
}

echo "\n✓ Done — $totalInserted new compatibility records inserted.\n";

