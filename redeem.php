<?php
header('Content-Type: application/json; charset=utf-8');

$DATA_FILE = '../codes.json';

// 确保数据文件存在
if (!file_exists($DATA_FILE)) {
    file_put_contents($DATA_FILE, json_encode(['codes' => [], 'logs' => []], JSON_UNESCAPED_UNICODE));
}

// 处理卡密验证
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputCode = isset($_POST['code']) ? trim($_POST['code']) : '';
    
    if (empty($inputCode)) {
        echo json_encode(['success' => false, 'message' => '卡密不能为空']);
        exit;
    }

    $data = json_decode(file_get_contents($DATA_FILE), true);
    $codes = $data['codes'] ?? [];
    $logs = $data['logs'] ?? [];

    $found = null;
    foreach ($codes as $index => $item) {
        if ($item['code'] === $inputCode && $item['is_active']) {
            $found = &$codes[$index];
            break;
        }
    }

    if ($found) {
        // 检查是否过期
        $expireTime = strtotime($found['expire_time']);
        if (time() > $expireTime) {
            echo json_encode(['success' => false, 'message' => '卡密已过期']);
            exit;
        }

        // 检查使用次数
        if ($found['current_uses'] >= $found['max_uses']) {
            echo json_encode(['success' => false, 'message' => '卡密已达到最大使用次数']);
            exit;
        }

        // 更新使用次数
        $found['current_uses']++;
        
        // 记录使用日志
        $logs[] = [
            'code' => $inputCode,
            'used_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'],
            'prize_content' => $found['prize_content']
        ];

        // 保存数据
        file_put_contents($DATA_FILE, json_encode(['codes' => $codes, 'logs' => $logs], JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'success' => true, 
            'message' => $found['prize_content'],
            'remaining' => $found['max_uses'] - $found['current_uses']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => '卡密无效或已被禁用，请检查后重试'
        ]);
    }
}
?>