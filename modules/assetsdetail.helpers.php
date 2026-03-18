<?php
/**
 * Helpers for modules/assetsdetail.php to keep the main template concise.
 */

if (!function_exists('assetdetail_get_category_definitions')) {
    function assetdetail_get_category_definitions(): array
    {
        return [
            'all'       => ['label' => 'เธชเธดเธเธ—เธฃเธฑเธเธขเนเธ—เธฑเนเธเธซเธกเธ”', 'icon' => 'fa-boxes',       'types' => []],
            'computers' => ['label' => 'เธเธญเธกเธเธดเธงเน€เธ•เธญเธฃเน',       'icon' => 'fa-desktop',     'types' => ['desktop', 'laptop']],
            'monitors'  => ['label' => 'เธเธญเธกเธญเธเธดเน€เธ•เธญเธฃเน',       'icon' => 'fa-tv',          'types' => ['monitor']],
            'network'   => ['label' => 'เธญเธธเธเธเธฃเธ“เนเน€เธเธฃเธทเธญเธเนเธฒเธข', 'icon' => 'fa-network-wired','types' => ['network']],
            'printers'  => ['label' => 'เน€เธเธฃเธทเนเธญเธเธเธดเธกเธเน',      'icon' => 'fa-print',       'types' => ['printer']],
            'phones'    => ['label' => 'เนเธ—เธฃเธจเธฑเธเธ—เน/เธกเธทเธญเธ–เธทเธญ',   'icon' => 'fa-mobile-alt',  'types' => ['mobile','phone']],
            'software'  => ['label' => 'เธเธญเธเธ•เนเนเธงเธฃเน',         'icon' => 'fa-compact-disc','types' => ['software']],
            'other'     => ['label' => 'เธญเธทเนเธเน',              'icon' => 'fa-cube',        'types' => ['other']],
        ];
    }
}

if (!function_exists('assetdetail_count_categories')) {
    function assetdetail_count_categories(mysqli $db, array $categories): array
    {
        $counts = [];
        foreach ($categories as $key => $definition) {
            if ($key === 'all') {
                $result = $db->query("SELECT COUNT(*) AS cnt FROM assets");
                $counts[$key] = (int)($result->fetch_assoc()['cnt'] ?? 0);
                continue;
            }

            $types = $definition['types'];
            if (empty($types)) {
                $counts[$key] = 0;
                continue;
            }

            $placeholders = implode(',', array_fill(0, count($types), '?'));
            $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM assets WHERE asset_type IN ($placeholders)");
            if ($stmt === false) {
                $counts[$key] = 0;
                continue;
            }
            $stmt->bind_param(str_repeat('s', count($types)), ...$types);
            $stmt->execute();
            $result = $stmt->get_result();
            $counts[$key] = (int)($result->fetch_assoc()['cnt'] ?? 0);
        }

        return $counts;
    }
}

if (!function_exists('assetdetail_handle_post_actions')) {
    function assetdetail_handle_post_actions(mysqli $db, int $assetId, bool $isAdmin): array
    {
        $message = '';
        $messageType = '';
        $action = '';

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return compact('message', 'messageType', 'action');
        }

        $rateLimit = rate_limit_check('module_assetsdetail_post', 50, 60);
        if (!$rateLimit['allowed']) {
            security_audit_log('rate_limit_blocked', [
                'module' => 'assetsdetail',
                'retry_after' => $rateLimit['retry_after'],
            ]);
            $message = 'Too many requests. Retry in ' . $rateLimit['retry_after'] . ' seconds';
            $messageType = 'error';
            return compact('message', 'messageType', 'action');
        }

        $action = $_POST['action'] ?? '';
        $restricted = ['add_repair', 'add_borrow', 'return_asset', 'add_transfer'];
        if (!$isAdmin && in_array($action, $restricted, true)) {
            security_audit_log('access_denied', [
                'module' => 'assetsdetail',
                'action' => $action,
                'asset_id' => $assetId,
            ]);
            $message = 'Access denied';
            $messageType = 'error';
            $action = '';
            return compact('message', 'messageType', 'action');
        }

        switch ($action) {
            case 'add_repair':
                if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                    $message = 'Invalid CSRF token';
                    $messageType = 'error';
                    break;
                }
                $stmt = $db->prepare("INSERT INTO asset_repairs (asset_id,repair_date,problem_desc,repair_detail,repair_cost,vendor,technician,status,warranty_claim,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $cost = (float)($_POST['repair_cost'] ?? 0);
                $stmt->bind_param('isssdsssii',
                    $assetId,
                    $_POST['repair_date'] ?? null,
                    sanitize($_POST['problem_desc'] ?? ''),
                    sanitize($_POST['repair_detail'] ?? ''),
                    $cost,
                    sanitize($_POST['vendor'] ?? ''),
                    sanitize($_POST['technician'] ?? ''),
                    sanitize($_POST['repair_status'] ?? ''),
                    isset($_POST['warranty_claim']) ? 1 : 0,
                    $_SESSION['user_id']
                );
                if ($stmt->execute()) {
                    if (($_POST['repair_status'] ?? '') === 'in_progress') {
                        $upd = $db->prepare("UPDATE assets SET status = 'maintenance' WHERE asset_id = ?");
                        $upd->bind_param('i', $assetId);
                        $upd->execute();
                    }
                    logActivity($_SESSION['user_id'], 'เน€เธเธดเนเธกเธเธฃเธฐเธงเธฑเธ•เธดเธเนเธญเธก', 'Assets', "Asset ID: $assetId");
                    $message = 'เธเธฑเธเธ—เธถเธเธเธฃเธฐเธงเธฑเธ•เธดเธเธฒเธฃเธเนเธญเธกเน€เธฃเธตเธขเธเธฃเนเธญเธข';
                    $messageType = 'success';
                } else {
                    $message = 'เน€เธเธดเธ”เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”: ' . $stmt->error;
                    $messageType = 'error';
                }
                break;
            case 'add_borrow':
                if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                    $message = 'Invalid CSRF token';
                    $messageType = 'error';
                    break;
                }
                $stmt = $db->prepare("INSERT INTO asset_borrows (asset_id,borrower_id,approved_by,borrow_date,expected_return,purpose,condition_out,created_by,status) VALUES (?,?,?,?,?,?,?,?,'borrowed')");
                $borrowerId = (int)($_POST['borrower_id'] ?? 0);
                $approvedBy = $isAdmin ? $_SESSION['user_id'] : null;
                $stmt->bind_param('iiissssi',
                    $assetId,
                    $borrowerId,
                    $approvedBy,
                    $_POST['borrow_date'] ?? null,
                    !empty($_POST['expected_return']) ? $_POST['expected_return'] : null,
                    sanitize($_POST['purpose'] ?? ''),
                    sanitize($_POST['condition_out'] ?? ''),
                    $_SESSION['user_id']
                );
                if ($stmt->execute()) {
                    $upd = $db->prepare("UPDATE assets SET status = 'inactive' WHERE asset_id = ?");
                    $upd->bind_param('i', $assetId);
                    $upd->execute();
                    logActivity($_SESSION['user_id'], 'เธเธฑเธเธ—เธถเธเธเธฒเธฃเธขเธทเธก', 'Assets', "Asset ID: $assetId เธเธนเนเธขเธทเธก: $borrowerId");
                    $message = 'เธเธฑเธเธ—เธถเธเธเธฒเธฃเธขเธทเธกเน€เธฃเธตเธขเธเธฃเนเธญเธข';
                    $messageType = 'success';
                } else {
                    $message = 'เน€เธเธดเธ”เธเนเธญเธเธดเธ”เธเธฅเธฒเธ”: ' . $stmt->error;
                    $messageType = 'error';
                }
                break;
            case 'return_asset':
                if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                    $message = 'Invalid CSRF token';
                    $messageType = 'error';
                    break;
                }
                $borrowId    = (int)($_POST['borrow_id'] ?? 0);
                $stmt = $db->prepare("UPDATE asset_borrows SET actual_return=?, condition_in=?, status='returned' WHERE borrow_id=?");
                $stmt->bind_param('ssi', $_POST['actual_return'] ?? null, sanitize($_POST['condition_in'] ?? ''), $borrowId);
                if ($stmt->execute()) {
                    $upd = $db->prepare("UPDATE assets SET status = 'active' WHERE asset_id = ?");
                    $upd->bind_param('i', $assetId);
                    $upd->execute();
                    logActivity($_SESSION['user_id'], 'เธเธทเธเธญเธธเธเธเธฃเธ“เน', 'Assets', "Asset ID: $assetId");
                    $message = 'เธเธฑเธเธ—เธถเธเธเธฒเธฃเธเธทเธเน€เธฃเธตเธขเธเธฃเนเธญเธข';
                    $messageType = 'success';
                }
                break;
            case 'add_transfer':
                if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                    $message = 'Invalid CSRF token';
                    $messageType = 'error';
                    break;
                }
                $fromUser    = !empty($_POST['from_user_id']) ? (int)$_POST['from_user_id'] : null;
                $toUser      = !empty($_POST['to_user_id'])   ? (int)$_POST['to_user_id']   : null;
                $stmt = $db->prepare("INSERT INTO asset_transfers (
                    asset_id,from_user_id,to_user_id,from_location,to_location,
                    from_dept,to_dept,transfer_date,reason,transferred_by
                ) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->bind_param('iiissssssi',
                    $assetId,
                    $fromUser,
                    $toUser,
                    sanitize($_POST['from_location'] ?? ''),
                    sanitize($_POST['to_location'] ?? ''),
                    sanitize($_POST['from_dept'] ?? ''),
                    sanitize($_POST['to_dept'] ?? ''),
                    $_POST['transfer_date'] ?? null,
                    sanitize($_POST['reason'] ?? ''),
                    $_SESSION['user_id']
                );
                $stmt->execute();

                $updates = [];
                $params = [];
                $typesUpdate = '';
                if ($toUser !== null) {
                    $updates[] = "assigned_to = ?";
                    $typesUpdate .= 'i';
                    $params[] = $toUser;
                }
                if (!empty($_POST['to_location'])) {
                    $updates[] = "location = ?";
                    $typesUpdate .= 's';
                    $params[] = sanitize($_POST['to_location']);
                }
                if (!empty($_POST['to_dept'])) {
                    $updates[] = "department = ?";
                    $typesUpdate .= 's';
                    $params[] = sanitize($_POST['to_dept']);
                }
                if ($updates) {
                    $params[] = $assetId;
                    $typesUpdate .= 'i';
                    $updStmt = $db->prepare("UPDATE assets SET " . implode(', ', $updates) . " WHERE asset_id = ?");
                    $updStmt->bind_param($typesUpdate, ...$params);
                    $updStmt->execute();
                }

                logActivity($_SESSION['user_id'], 'เนเธญเธเธขเนเธฒเธขเธชเธดเธเธ—เธฃเธฑเธเธขเน', 'Assets', "Asset ID: $assetId โ’ " . sanitize($_POST['to_location'] ?? ''));
                $message = 'เธเธฑเธเธ—เธถเธเธเธฒเธฃเนเธญเธเธขเนเธฒเธขเน€เธฃเธตเธขเธเธฃเนเธญเธข';
                $messageType = 'success';
                break;
            default:
                break;
        }

        return compact('message', 'messageType', 'action');
    }
}

if (!function_exists('assetdetail_fetch_asset_context')) {
    function assetdetail_fetch_asset_context(mysqli $db, int $assetId): array
    {
        $stmt = $db->prepare("SELECT a.*, u.full_name AS assigned_name FROM assets a LEFT JOIN users u ON a.assigned_to = u.user_id WHERE a.asset_id = ? LIMIT 1");
        $stmt->bind_param('i', $assetId);
        $stmt->execute();
        $assetRes = $stmt->get_result();
        if (!$assetRes || $assetRes->num_rows === 0) {
            return [];
        }
        $asset = $assetRes->fetch_assoc();

        $repStmt = $db->prepare("SELECT r.*, u.full_name as created_by_name FROM asset_repairs r LEFT JOIN users u ON r.created_by=u.user_id WHERE r.asset_id = ? ORDER BY r.repair_date DESC");
        $repStmt->bind_param('i', $assetId);
        $repStmt->execute();
        $repairs = $repStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $borStmt = $db->prepare("SELECT b.*, u.full_name as borrower_name, a.full_name as approved_name FROM asset_borrows b LEFT JOIN users u ON b.borrower_id=u.user_id LEFT JOIN users a ON b.approved_by=a.user_id WHERE b.asset_id = ? ORDER BY b.borrow_date DESC");
        $borStmt->bind_param('i', $assetId);
        $borStmt->execute();
        $borrows = $borStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $trnStmt = $db->prepare("SELECT t.*, fu.full_name as from_name, tu.full_name as to_name, by_u.full_name as by_name FROM asset_transfers t LEFT JOIN users fu ON t.from_user_id=fu.user_id LEFT JOIN users tu ON t.to_user_id=tu.user_id LEFT JOIN users by_u ON t.transferred_by=by_u.user_id WHERE t.asset_id = ? ORDER BY t.transfer_date DESC");
        $trnStmt->bind_param('i', $assetId);
        $trnStmt->execute();
        $transfers = $trnStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $users = $db->query("SELECT user_id, full_name FROM users WHERE status='active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

        $depData = assetdetail_calculate_depreciation($asset);
        $repairTotal = array_sum(array_map(fn($item) => (float)$item['repair_cost'], $repairs));
        $activeBorrow = assetdetail_get_active_borrow($borrows);

        return compact('asset', 'repairs', 'borrows', 'transfers', 'users', 'depData', 'repairTotal', 'activeBorrow');
    }
}

if (!function_exists('assetdetail_calculate_depreciation')) {
    function assetdetail_calculate_depreciation(array $asset): ?array
    {
        if (empty($asset['purchase_price']) || empty($asset['purchase_date'])) {
            return null;
        }

        $purchasePrice = (float)$asset['purchase_price'];
        $salvageValue = (float)($asset['salvage_value'] ?? 0);
        $usefulLife = max((int)($asset['useful_life_years'] ?? 5), 1);
        $yearlyDep = ($purchasePrice - $salvageValue) / $usefulLife;
        $yearsUsed = max(date('Y') - date('Y', strtotime($asset['purchase_date'])), 0);
        $totalDep = min($yearlyDep * $yearsUsed, $purchasePrice - $salvageValue);
        $currentValue = max($purchasePrice - $totalDep, $salvageValue);
        $depPercent = $purchasePrice > 0 ? round($totalDep / $purchasePrice * 100) : 0;

        return compact('purchasePrice', 'salvageValue', 'usefulLife', 'yearlyDep', 'yearsUsed', 'totalDep', 'currentValue', 'depPercent');
    }
}

if (!function_exists('assetdetail_get_active_borrow')) {
    function assetdetail_get_active_borrow(array $borrows): ?array
    {
        foreach ($borrows as $borrow) {
            if (($borrow['status'] ?? '') === 'borrowed') {
                return $borrow;
            }
        }
        return null;
    }
}
