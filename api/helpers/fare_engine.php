<?php
// ── Configurable fare resolution ──────────────────────────────────────────────

/**
 * Resolve the best matching active fare product.
 * Conditions: media, passenger category, optional route/zone, effective dates, priority.
 */
function resolve_fare(array $criteria): ?array {
    $db = get_db();
    $orgId = $criteria['organization_id'] ?? null;
    $media = strtoupper($criteria['media'] ?? 'QR');           // QR | CARD
    $category = strtoupper($criteria['passenger_category'] ?? 'STUDENT');
    $routeCode = $criteria['route_code'] ?? null;
    $zoneCode = $criteria['zone_code'] ?? null;
    $at = $criteria['at'] ?? date('Y-m-d');

    $sql = "
        SELECT * FROM fare_products
        WHERE active = 1
          AND (effective_from IS NULL OR effective_from <= ?)
          AND (effective_to IS NULL OR effective_to >= ?)
          AND (passenger_category = ? OR passenger_category = 'ALL')
          AND (media_types LIKE ? OR media_types = 'ALL')
    ";
    $params = [$at, $at, $category, '%' . $media . '%'];

    if ($orgId) {
        $sql .= " AND (organization_id = ? OR organization_id IS NULL)";
        $params[] = $orgId;
    }
    if ($routeCode) {
        $sql .= " AND (route_code IS NULL OR route_code = ? OR route_code = '')";
        $params[] = $routeCode;
    }
    if ($zoneCode) {
        $sql .= " AND (zone_code IS NULL OR zone_code = ? OR zone_code = '')";
        $params[] = $zoneCode;
    }

    $sql .= " ORDER BY priority ASC, id DESC LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if ($row) return $row;

    // Ultimate fallback: system_config student_fare for QR/STUDENT
    if ($media === 'QR' && $category === 'STUDENT') {
        return [
            'id' => null,
            'code' => 'STUDENT_FLAT_FALLBACK',
            'name' => 'Student Ticket',
            'product_type' => 'FLAT',
            'amount' => (float)get_config('student_fare', 200),
            'currency' => (string)get_config('currency', 'TZS'),
            'validity_hours' => (int)get_config('ticket_validity_hours', 24),
            'passenger_category' => 'STUDENT',
        ];
    }
    return null;
}

function list_fare_products(?int $orgId = null, bool $activeOnly = true): array {
    $db = get_db();
    $sql = "SELECT * FROM fare_products WHERE 1=1";
    $params = [];
    if ($activeOnly) $sql .= " AND active = 1";
    if ($orgId) {
        $sql .= " AND (organization_id = ? OR organization_id IS NULL)";
        $params[] = $orgId;
    }
    $sql .= " ORDER BY priority ASC, name ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
