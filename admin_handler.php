<?php
header('Content-Type: application/json; charset=utf-8');

$DATA_FILE = '../codes.json';

// 确保数据文件存在
if (!file_exists($DATA_FILE)) {
    file_put_contents($DATA_FILE, json_encode(['codes' => [], 'logs' => []], JSON_UNESCAPED_UNICODE));
}

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

switch ($action) {
    case 'generate':
        handleGenerate();
        break;
    case 'list':
        handleList();
        break;
    case 'stats':
        handleStats();
        break;
    case 'toggle':
        handleToggle();
        break;
    case 'delete':
        handleDelete();
        break;
    case 'edit':
        handleEdit();
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效的操作']);
        break;
}

function handleGenerate() {
    global $DATA_FILE;
    
    $count = intval($_POST['count'] ?? 0);
    $maxUses = intval($_POST['max_uses'] ?? 1);
    $expireTime = $_POST['expire_time'] ?? '';
    $prizeContents = $_POST['prize_contents'] ?? '';
    $description = $_POST['description'] ?? '';
    
    $prizeLines = array_filter(array_map('trim', explode("\n", $prizeContents)), function($line) {
        return !empty($line);
    });
    
    $actualCount = count($prizeLines);
    
    if ($actualCount === 0) {
        echo json_encode(['success' => false, 'message' => '请至少输入一行中奖内容']);
        return;
    }
    
    if ($maxUses < 1) {
        echo json_encode(['success' => false, 'message' => '最大使用次数必须大于0']);
        return;
    }
    
    if (empty($expireTime)) {
        echo json_encode(['success' => false, 'message' => '请设置过期时间']);
        return;
    }

    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    $newCodes = [];
    foreach ($prizeLines as $prizeContent) {
        $code = generateRandomCode();
        $newCodes[] = [
            'code' => $code,
            'max_uses' => $maxUses,
            'current_uses' => 0,
            'expire_time' => $expireTime,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => true,
            'prize_content' => $prizeContent,
            'description' => $description
        ];
    }
    
    $data['codes'] = array_merge($codes, $newCodes);
    file_put_contents($DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'message' => '成功生成'.$actualCount.'个卡密',
        'codes' => array_column($newCodes, 'code')
    ]);
}

function handleList() {
    global $DATA_FILE;
    
    // 检查是否是纯文本输出请求
    if (isset($_GET['format']) && $_GET['format'] === 'text') {
        $data = json_decode(file_get_contents($DATA_FILE), true);
        $codes = $data['codes'] ?? [];
        
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="codes.txt"');
        
        foreach ($codes as $code) {
            echo $code['code'] . "\n";
        }
        exit;
    }
    
    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    echo json_encode(['success' => true, 'codes' => $codes]);
}

function handleStats() {
    global $DATA_FILE;
    
    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    $stats = [
        'total_codes' => count($codes),
        'active_codes' => 0,
        'expired_codes' => 0,
        'disabled_codes' => 0,
        'used_codes' => 0,
        'unused_codes' => 0
    ];
    
    foreach ($codes as $code) {
        if (!$code['is_active']) {
            $stats['disabled_codes']++;
            continue;
        }
        
        if (strtotime($code['expire_time']) < time()) {
            $stats['expired_codes']++;
            continue;
        }
        
        $stats['active_codes']++;
        
        if ($code['current_uses'] > 0) {
            $stats['used_codes']++;
        } else {
            $stats['unused_codes']++;
        }
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
}

function handleToggle() {
    global $DATA_FILE;
    
    $code = $_POST['code'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => '卡密不能为空']);
        return;
    }
    
    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    $found = false;
    foreach ($codes as &$item) {
        if ($item['code'] === $code) {
            $item['is_active'] = !$item['is_active'];
            $found = true;
            break;
        }
    }
    
    if ($found) {
        file_put_contents($DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => '卡密状态已更新']);
    } else {
        echo json_encode(['success' => false, 'message' => '未找到指定卡密']);
    }
}

function handleDelete() {
    global $DATA_FILE;
    
    $code = $_POST['code'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => '卡密不能为空']);
        return;
    }
    
    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    $newCodes = array_filter($codes, function($item) use ($code) {
        return $item['code'] !== $code;
    });
    
    if (count($newCodes) < count($codes)) {
        $data['codes'] = array_values($newCodes);
        file_put_contents($DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => '卡密已删除']);
    } else {
        echo json_encode(['success' => false, 'message' => '未找到指定卡密']);
    }
}

function handleEdit() {
    global $DATA_FILE;
    
    $code = $_POST['code'] ?? '';
    $prizeContent = $_POST['prize_content'] ?? '';
    
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => '卡密不能为空']);
        return;
    }
    
    if (empty($prizeContent)) {
        echo json_encode(['success' => false, 'message' => '中奖内容不能为空']);
        return;
    }
    
    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    
    $found = false;
    foreach ($codes as &$item) {
        if ($item['code'] === $code) {
            $item['prize_content'] = $prizeContent;
            $found = true;
            break;
        }
    }
    
    if ($found) {
        file_put_contents($DATA_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => '中奖内容已更新']);
    } else {
        echo json_encode(['success' => false, 'message' => '未找到指定卡密']);
    }
}

function generateRandomCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $code;
}
?>