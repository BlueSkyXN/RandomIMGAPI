<?php
// 开关变量
$use_hardcoded_config = true;

// 硬编码的全部配置信息
$hardcoded_config = [
    'Redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 15,
        'conf_database' => 14,
        'username' => '',
        'password' => ''
    ],
    'File' => [
        'file_dir' => 'imgurl.txt'
    ],
    'Mode' => [
        'current_mode' => 'Conf',
        'redirect_mode' => 'Origin'  // 可以是 'Origin', 'JS', 或 'META'
    ]
];

// 如果开关关闭，从配置文件读取
if (!$use_hardcoded_config) {
    $config = parse_ini_file("api.conf", true);
} else {
    $config = $hardcoded_config;
}

// 创建Redis对象并连接
$redis = new Redis();
$redis->connect($config['Redis']['host'], $config['Redis']['port']);

// 如果配置中有用户名和密码，则进行身份验证
if (!empty($config['Redis']['username']) && !empty($config['Redis']['password'])) {
    $redis->auth([$config['Redis']['username'], $config['Redis']['password']]);
}

// 选择Redis主数据库
$redis->select($config['Redis']['database']);

// 获取当前模式
$current_mode = $config['Mode']['current_mode'];

// 切换模式
if ($current_mode == "Conf" || $current_mode == "Load") {
    $file_dir = $config['File']['file_dir'];
    $lines = file($file_dir, FILE_IGNORE_NEW_LINES);
    $count = count($lines);
    
    // 清空旧的数据
    $redis->flushDB();
    
    // 加载新的URL
    foreach ($lines as $index => $url) {
        $redis->set($index, $url);
    }

    // 更新图片URL数量
    $redis->select($config['Redis']['conf_database']);
    $redis->set("img_count", $count);

    // 如果是Conf模式，重新加载配置到conf_database
    if ($current_mode == "Conf") {
        foreach ($config as $section => $items) {
            foreach ($items as $key => $value) {
                $redis->hSet($section, $key, $value);
            }
        }
    }
    echo "Data and config reloaded based on current_mode: $current_mode";
    exit;
}

// 如果是Work模式，从conf_database获取图片URL数量
if ($current_mode == "Work") {
    $redis->select($config['Redis']['conf_database']);
    $count = $redis->get("img_count");
    $redis->select($config['Redis']['database']);
}

// 获取重定向模式
$redirect_mode = $config['Mode']['redirect_mode'];

// 生成随机数并获取对应的URL
if ($count > 0) {
    $rand_index = rand(0, $count - 1);
    $url = $redis->get($rand_index);
    
    // 根据配置的重定向模式进行重定向
    switch ($redirect_mode) {
        case 'Origin':
            header("Location: " . $url);
            break;
        case 'JS':
            echo '<script type="text/javascript">
                    location.replace("' . $url . '");
                  </script>';
            break;
        case 'META':
            echo '<meta http-equiv="refresh" content="0;url=' . $url . '">';
            break;
        default:
            header("Location: " . $url);  // 默认使用原生PHP重定向
            break;
    }
} else {
    echo "No URLs available.";
}
?>