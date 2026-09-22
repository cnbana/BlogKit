<?php
// 安装脚本 - 多步骤安装

// 定义系统常量
define('ROOT_PATH', __DIR__);
define('CORE_PATH', ROOT_PATH . '/core');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('INSTALL_LOCK', ROOT_PATH . '/install.lock');

// 启动会话（安全配置）
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// 当前步骤（需要先确定步骤，再判断锁文件是否拦截）
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$maxStep = 4;

// 安全检查：如果已存在安装锁定文件，禁止重新安装（step=4 安装完成页除外）
if (file_exists(INSTALL_LOCK) && $step != 4) {
    header('HTTP/1.1 403 Forbidden');
    die('系统已安装。如需重新安装，请删除 install.lock 文件。');
}

// 安全检查：防止直接访问安装脚本
if (!isset($_SERVER['HTTP_REFERER']) && !isset($_POST['csrf_token'])) {
    // 只允许从本域名访问
    $serverName = $_SERVER['SERVER_NAME'] ?? '';
    $httpReferer = $_SERVER['HTTP_REFERER'] ?? '';
    if (!empty($httpReferer) && strpos($httpReferer, $serverName) === false) {
        die('非法访问！');
    }
}

// 生成CSRF令牌
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 验证步骤有效性
if ($step < 1 || $step > $maxStep) {
    $step = 1;
}

// 步骤验证：确保用户按顺序访问步骤
$lastStep = isset($_SESSION['last_step']) ? (int)$_SESSION['last_step'] : 0;

// 严格验证步骤顺序：只能访问当前允许的步骤
// 允许的步骤是：1 或 lastStep + 1
$allowedSteps = [1];
if ($lastStep > 0) {
    $allowedSteps[] = $lastStep + 1;
}

// 只允许访问允许的步骤
if (!in_array($step, $allowedSteps)) {
    // 如果尝试访问不允许的步骤，重置到最后完成的步骤或步骤1
    $step = $lastStep > 0 ? $lastStep : 1;
}

// 安装结果
$error = '';
$success = '';
$installProgress = 0;

// 系统需求
$requirements = [
    'php_version' => ['name' => 'PHP 版本', 'required' => '7.4', 'current' => PHP_VERSION, 'check' => version_compare(PHP_VERSION, '7.4', '>='), 'description' => 'BlogKit 需要 PHP 7.4 或更高版本'],
    'mysql_extension' => ['name' => 'MySQLi 扩展', 'required' => '是', 'current' => extension_loaded('mysqli') ? '已加载' : '未加载', 'check' => extension_loaded('mysqli'), 'description' => '用于数据库连接'],
    'pdo_extension' => ['name' => 'PDO 扩展', 'required' => '是', 'current' => extension_loaded('pdo_mysql') ? '已加载' : '未加载', 'check' => extension_loaded('pdo_mysql'), 'description' => '用于数据库操作'],
    'gd_extension' => ['name' => 'GD 扩展', 'required' => '推荐', 'current' => extension_loaded('gd') ? '已加载' : '未加载', 'check' => true, 'description' => '用于图片处理'],
    'curl_extension' => ['name' => 'cURL 扩展', 'required' => '推荐', 'current' => extension_loaded('curl') ? '已加载' : '未加载', 'check' => true, 'description' => '用于HTTP请求'],
    'fileinfo_extension' => ['name' => 'Fileinfo 扩展', 'required' => '推荐', 'current' => extension_loaded('fileinfo') ? '已加载' : '未加载', 'check' => true, 'description' => '用于文件类型检测'],
    'write_permission' => ['name' => '目录写入权限', 'required' => '是', 'current' => '', 'check' => true, 'description' => '确保系统能够写入必要的文件和目录'],
];

// 检查目录写入权限
$checkDirs = [
    ROOT_PATH,
    CORE_PATH . '/config',
    ROOT_PATH . '/uploads', // 上传目录统一使用根目录 uploads，与 UPLOADS_PATH 保持一致，避免安装时在 storage 下创建冗余目录
    ROOT_PATH . '/themes',
    STORAGE_PATH . '/cache',
    STORAGE_PATH . '/logs',
];

$writePermission = true;
$dirStatus = [];
foreach ($checkDirs as $dir) {
    // 确保目录存在
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $dirStatus[$dir] = false;
            $writePermission = false;
            continue;
        }
    }
    
    if (is_writable($dir)) {
        $dirStatus[$dir] = true;
    } else {
        $dirStatus[$dir] = false;
        $writePermission = false;
    }
}

$requirements['write_permission']['current'] = $writePermission ? '符合要求' : '不符合要求';
$requirements['write_permission']['check'] = $writePermission;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF验证失败！');
    }
    
    switch ($step) {
        case 1:
            // 同意协议，进入下一步
            $_SESSION['last_step'] = 1;
            header('Location: install.php?step=2');
            exit;
            break;
        
        case 2:
            // 环境检查通过，进入下一步
            $_SESSION['last_step'] = 2;
            header('Location: install.php?step=3');
            exit;
            break;
        
        case 3:
            // 获取数据库配置
            $config = [
                'host' => $_POST['host'],
                'port' => (int)$_POST['port'],
                'username' => $_POST['username'],
                'password' => $_POST['password'],
                'database' => $_POST['database'],
                'prefix' => $_POST['prefix'],
                'charset' => $_POST['charset'],
            ];
            
            // 获取管理员配置
            $adminConfig = [
                'username' => isset($_POST['admin_username']) ? $_POST['admin_username'] : 'admin',
                'password' => isset($_POST['admin_password']) ? $_POST['admin_password'] : '12345678',
                'email' => isset($_POST['admin_email']) ? $_POST['admin_email'] : 'admin@example.com',
            ];
            
            // 安全验证：过滤输入（密码除外，以免破坏特殊字符）
            foreach ($config as &$value) {
                $value = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
            // 单独处理管理员配置，密码不做htmlspecialchars破坏原始字符
            $adminConfig['username'] = htmlspecialchars(trim($adminConfig['username']), ENT_QUOTES, 'UTF-8');
            $adminConfig['email'] = htmlspecialchars(trim($adminConfig['email']), ENT_QUOTES, 'UTF-8');
            $adminConfig['password'] = trim($adminConfig['password']);
            // 保存管理员信息到session（用于安装完成页展示）
            $_SESSION['install_admin_username'] = $adminConfig['username'];
            $_SESSION['install_admin_password'] = $adminConfig['password'];
            $_SESSION['install_admin_email'] = $adminConfig['email'];
            
            // 验证必要字段
            if (empty($config['host']) || empty($config['username']) || empty($config['database'])) {
                $error = '主机地址、用户名和数据库名不能为空';
            } elseif (empty($adminConfig['username']) || empty($adminConfig['password'])) {
                $error = '管理员用户名和密码不能为空';
            } elseif (strlen($adminConfig['password']) < 8) {
                $error = '管理员密码长度至少8位';
            } else {
                try {
                    // 连接数据库服务器
                    $conn = mysqli_connect(
                        $config['host'],
                        $config['username'],
                        $config['password'],
                        '',
                        $config['port']
                    );
                    
                    if (!$conn) {
                        throw new Exception('无法连接到数据库服务器: ' . mysqli_connect_error());
                    }
                    
                    // 设置字符集
                    mysqli_set_charset($conn, $config['charset']);
                    
                    // 创建数据库
                    $sql = "CREATE DATABASE IF NOT EXISTS `{$config['database']}` DEFAULT CHARACTER SET {$config['charset']} COLLATE {$config['charset']}_unicode_ci";
                    if (!mysqli_query($conn, $sql)) {
                        throw new Exception('创建数据库失败: ' . mysqli_error($conn));
                    }
                    
                    // 选择数据库
                    mysqli_select_db($conn, $config['database']);
                    
                    // 读取SQL文件
                    $sqlFile = CORE_PATH . '/config/install.sql';
                    if (!file_exists($sqlFile)) {
                        throw new Exception('SQL文件不存在');
                    }
                    
                    $sql = file_get_contents($sqlFile);
                    
                    // 替换表前缀
                    $sql = str_replace('bk_', $config['prefix'], $sql);
                    
                    // 自动获取当前域名，替换SQL中的固定域名
                    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                    $currentUrl = $protocol . '://' . $host;
                    $sql = str_replace('http://localhost', $currentUrl, $sql);
                    
                    // 替换管理员信息
                    $hashedPassword = password_hash($adminConfig['password'], PASSWORD_DEFAULT);
                    $sql = str_replace('admin', $adminConfig['username'], $sql);
                    $sql = str_replace('$2y$10$examplehash', $hashedPassword, $sql);
                    $sql = str_replace('admin@example.com', $adminConfig['email'], $sql);
                    
                    // 执行SQL语句
                    $queries = explode(';', $sql);
                    $totalQueries = count($queries);
                    $executedQueries = 0;
                    
                    foreach ($queries as $query) {
                        $query = trim($query);
                        if (!empty($query)) {
                            if (!mysqli_query($conn, $query)) {
                                throw new Exception('执行SQL失败: ' . mysqli_error($conn) . '\nSQL: ' . $query);
                            }
                            $executedQueries++;
                            // 更新安装进度
                            $installProgress = round(($executedQueries / $totalQueries) * 100);
                        }
                    }
                    
                    // 保存配置文件
                    $configContent = "<?php\nreturn [\n";
                    foreach ($config as $key => $value) {
                        $configContent .= "    '{$key}' => '{$value}',\n";
                    }
                    $configContent .= "];\n";
                    
                    if (!file_put_contents(CORE_PATH . '/config/database.php', $configContent)) {
                        throw new Exception('无法写入配置文件，请确保目录有写入权限');
                    }
                    
                    // 设置配置文件权限（更安全）
                    chmod(CORE_PATH . '/config/database.php', 0644);
                    
                    // 生成安装锁文件（仅写时间戳，不泄露敏感信息）
                    if (!file_put_contents(INSTALL_LOCK, date('Y-m-d H:i:s'))) {
                        throw new Exception('无法创建安装锁文件，请确保目录有写入权限');
                    }
                    
                    // 设置安装锁文件权限
                    chmod(INSTALL_LOCK, 0644);
                    
                    // 关闭连接
                    mysqli_close($conn);
                    
                    // 更新最后步骤
                    $_SESSION['last_step'] = 3;
                    
                    // 安装成功，进入完成页面
                    header('Location: install.php?step=4');
                    exit;
                    
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }
            break;
        
        case 4:
            // 安装完成，显示完成页面，无需跳转
            break;
    }
}

// 页面HTML

// 安全HTTP头
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('X-Permitted-Cross-Domain-Policies: none');
header('X-Download-Options: noopen');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BlogKit 安装 - 步骤 <?php echo $step; ?>/<?php echo $maxStep; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 30px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 40px;
            color: #333;
            font-size: 28px;
            font-weight: 600;
        }
        h2 {
            margin-bottom: 25px;
            color: #495057;
            font-size: 22px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        h3 {
            margin-bottom: 15px;
            color: #6c757d;
            font-size: 18px;
        }
        
        /* 步骤导航 - 现代线性进度条 */
        .steps {
            margin-bottom: 50px;
        }
        .progress-container {
            background-color: #e9ecef;
            border-radius: 20px;
            height: 8px;
            width: 100%;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        .progress-bar {
            background-color: #007bff;
            border-radius: 20px;
            height: 100%;
            width: 0%;
            transition: width 0.5s ease-in-out;
            position: absolute;
            left: 0;
            top: 0;
        }
        /* 根据当前步骤设置进度条宽度 */
        <?php if ($step == 1) { ?> .progress-bar { width: 25%; } <?php } ?>
        <?php if ($step == 2) { ?> .progress-bar { width: 50%; } <?php } ?>
        <?php if ($step == 3) { ?> .progress-bar { width: 75%; } <?php } ?>
        <?php if ($step == 4) { ?> .progress-bar { width: 100%; } <?php } ?>
        
        .step-labels {
            display: flex;
            justify-content: space-between;
        }
        .step-item {
            text-align: center;
            flex: 1;
            position: relative;
            transition: all 0.3s ease;
        }
        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            color: #6c757d;
            border-radius: 50%;
            line-height: 40px;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .step-text {
            display: block;
            font-size: 14px;
            color: #6c757d;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        /* 当前步骤样式 */
        .step-item.active .step-number {
            background-color: #007bff;
            color: #fff;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        }
        .step-item.active .step-text {
            color: #007bff;
            font-weight: bold;
        }
        
        /* 已完成步骤样式 */
        .step-item.done .step-number {
            background-color: #28a745;
            color: #fff;
            transform: scale(1.05);
        }
        .step-item.done .step-text {
            color: #28a745;
            font-weight: 500;
        }
        
        /* 内容区域 */
        .content {
            margin: 40px 0;
        }
        .content p {
            margin-bottom: 20px;
            line-height: 1.8;
            color: #6c757d;
            font-size: 16px;
        }
        
        /* 协议样式 */
        .agreement {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 30px;
            background-color: #f8f9fa;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .agreement h3 {
            margin-bottom: 20px;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
        }
        .agreement ul {
            margin-left: 25px;
            margin-bottom: 20px;
        }
        .agreement li {
            margin-bottom: 10px;
            color: #6c757d;
            line-height: 1.5;
        }
        
        /* 检查项样式 */
        .checklist {
            margin-bottom: 30px;
        }
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 6px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .check-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        .check-item.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        .check-item.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        .check-item.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
        }
        .check-item .check-info {
            flex: 1;
        }
        .check-item .check-info strong {
            display: block;
            margin-bottom: 5px;
            color: #495057;
        }
        .check-item .check-info .check-desc {
            font-size: 14px;
            color: #6c757d;
            line-height: 1.4;
        }
        .check-status {
            font-weight: bold;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 14px;
            white-space: nowrap;
        }
        .check-status.success {
            color: #155724;
            background-color: #c3e6cb;
        }
        .check-status.error {
            color: #721c24;
            background-color: #f5c6cb;
        }
        .check-status.warning {
            color: #856404;
            background-color: #ffeeba;
        }
        
        /* 目录权限 */
        .dir-list {
            margin-top: 25px;
        }
        .dir-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .dir-item:last-child {
            border-bottom: none;
        }
        .dir-item.success {
            color: #28a745;
        }
        .dir-item.error {
            color: #dc3545;
        }
        .dir-item .dir-status {
            font-weight: 500;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 15px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }
        .form-section h3 {
            margin-bottom: 20px;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
        }
        
        /* 按钮样式 */
        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background-color: #007bff;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        .btn-secondary {
            background-color: #6c757d;
            color: #fff;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }
        .btn-success {
            background-color: #28a745;
            color: #fff;
        }
        .btn-success:hover {
            background-color: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
        .btn-danger {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* 提示信息 */
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 6px;
            border-left: 4px solid #dc3545;
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.1);
        }
        .success {
    background-color: #d4edda;
    color: #155724;
    padding: 10px 10px;
    margin-bottom: 5px;
    border-radius: 6px;
    border-left: 3px solid #28a745;
        }
        
        /* 完成页面 */
        .completion {
            text-align: center;
            padding: 60px 0;
        }
        .completion-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 30px;
            animation: bounce 0.6s ease;
        }
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-20px); }
            60% { transform: translateY(-10px); }
        }
        .completion h2 {
            margin-bottom: 30px;
            color: #28a745;
            font-size: 28px;
        }
        .completion p {
            font-size: 18px;
            color: #6c757d;
            margin-bottom: 40px;
        }
        .completion-buttons {
            margin-top: 40px;
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        .admin-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 25px;
            margin: 30px auto;
            max-width: 500px;
            text-align: left;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.1);
        }
        .admin-info h3 {
            margin-bottom: 15px;
            color: #155724;
            font-size: 18px;
            text-align: center;
        }
        .admin-info ul {
            margin-left: 20px;
        }
        .admin-info li {
            margin-bottom: 10px;
            color: #155724;
            line-height: 1.5;
        }
        
        /* 安全提示 */
        .security-tips {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 20px;
            margin-top: 40px;
            box-shadow: 0 2px 8px rgba(255, 193, 7, 0.1);
        }
        .security-tips h3 {
            margin-bottom: 15px;
            color: #856404;
            font-size: 16px;
            font-weight: 600;
        }
        .security-tips ul {
            margin-left: 20px;
        }
        .security-tips li {
            margin-bottom: 8px;
            color: #856404;
            line-height: 1.5;
        }
        
        /* 安装进度 */
        .install-progress {
            margin: 30px 0;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        .progress-bar-container {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar-fill {
            height: 100%;
            background-color: #28a745;
            border-radius: 10px;
            width: <?php echo $installProgress; ?>%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 20px;
            }
            .buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                text-align: center;
            }
            .step-text {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>BlogKit 安装</h1>
        
        <!-- 步骤导航 -->
        <div class="steps">
            <div class="progress-container">
                <div class="progress-bar"></div>
            </div>
            <div class="step-labels">
                <div class="step-item <?php echo $step >= 1 ? 'active' : ''; ?><?php echo $step > 1 ? ' done' : ''; ?>">
                    <div class="step-number">1</div>
                    <div class="step-text">系统介绍</div>
                </div>
                <div class="step-item <?php echo $step >= 2 ? 'active' : ''; ?><?php echo $step > 2 ? ' done' : ''; ?>">
                    <div class="step-number">2</div>
                    <div class="step-text">环境检查</div>
                </div>
                <div class="step-item <?php echo $step >= 3 ? 'active' : ''; ?><?php echo $step > 3 ? ' done' : ''; ?>">
                    <div class="step-number">3</div>
                    <div class="step-text">配置安装</div>
                </div>
                <div class="step-item <?php echo $step >= 4 ? 'active' : ''; ?>">
                    <div class="step-number">4</div>
                    <div class="step-text">安装完成</div>
                </div>
            </div>
        </div>
        
        <!-- 错误提示 -->
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <!-- 安装内容 -->
        <div class="content">
            <?php switch($step): case 1: ?>
                        <!-- 步骤1：系统介绍 -->
                    <h2>欢迎使用 BlogKit</h2>
                    <p>BlogKit 是一个基于 PHP7.4+MySQL 开发的现代化博客系统，具有清晰的目录结构、良好的扩展性和丰富的功能特性。</p>
                    <p>系统特点：</p>
                    <ul style="margin-left: 20px; margin-bottom: 20px; color: #6c757d;">
                        <li>现代化的后台管理界面</li>
                        <li>完善的文章管理功能</li>
                        <li>强大的用户权限系统</li>
                        <li>灵活的主题和插件机制</li>
                        <li>内置缓存系统提升性能</li>
                    </ul>
                    
                    <div class="agreement">
                        <h3>安装协议</h3>
                        <p>感谢您选择 BlogKit！在安装前，请仔细阅读以下协议：</p>
                        <ul>
                            <li>1. BlogKit 采用 MIT 许可证，可自由使用、修改和分发。</li>
                            <li>2. 安装过程将自动创建数据库、表结构和初始数据。</li>
                            <li>3. 请确保您拥有服务器的相应权限。</li>
                            <li>4. 安装完成后，请妥善保管您的管理员账号和密码。</li>
                            <li>5. 本系统仅供学习和研究使用，请勿用于非法用途。</li>
                        </ul>
                        <p>通过点击"我同意并继续"按钮，即表示您已阅读并同意上述协议。</p>
                    </div>
                    
                    <div class="buttons">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-primary">我同意并继续</button>
                        </form>
                    </div>
                    <?php break; ?>
                    
            <?php case 2: ?>
                    <!-- 步骤2：环境检查 -->
                    <h2>系统环境检查</h2>
                    <p>请检查您的服务器环境是否符合 BlogKit 的安装要求：</p>
                    
                    <div class="checklist">
                        <?php foreach ($requirements as $key => $req): ?>
                            <div class="check-item <?php echo $req['check'] ? 'success' : ($key == 'gd_extension' || $key == 'curl_extension' || $key == 'fileinfo_extension' ? 'success' : 'error'); ?>">
                                <div class="check-info">
                                    <strong><?php echo $req['name']; ?></strong>
                                    <span class="check-desc"><?php echo $req['description']; ?></span>
                                    <span class="check-desc">要求：<?php echo $req['required']; ?>，当前：<?php echo $req['current']; ?></span>
                                </div>
                                <span class="check-status <?php echo $req['check'] ? 'success' : ($key == 'gd_extension' || $key == 'curl_extension' || $key == 'fileinfo_extension' ? 'success' : 'error'); ?>">
                                    <?php echo $req['check'] ? '符合要求' : ($key == 'gd_extension' || $key == 'curl_extension' || $key == 'fileinfo_extension' ? '符合要求' : '不符合要求'); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 目录权限检查 -->
                    <h3>目录权限检查</h3>
                    <div class="dir-list">
                        <?php foreach ($dirStatus as $dir => $status): ?>
                            <div class="dir-item <?php echo $status ? 'success' : 'error'; ?>">
                                <span><?php echo $dir; ?></span>
                                <span class="dir-status <?php echo $status ? 'success' : 'error'; ?>">
                                    <?php echo $status ? '可写' : '不可写'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="buttons">
                        <form method="GET">
                            <input type="hidden" name="step" value="1">
                            <button type="submit" class="btn btn-secondary">上一步</button>
                        </form>
                        
                        <?php $allCheck = true; ?>
                        <?php foreach ($requirements as $key => $req): ?>
                            <?php if (!$req['check'] && !in_array($key, ['gd_extension', 'curl_extension', 'fileinfo_extension'])): ?>
                                <?php $allCheck = false; ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <?php if (!$writePermission): ?>
                            <?php $allCheck = false; ?>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-primary" <?php echo !$allCheck ? 'disabled' : ''; ?>>
                                继续安装
                            </button>
                        </form>
                    </div>
                    <?php break; ?>
                    
            <?php case 3: ?>
                    <!-- 步骤3：数据库配置 -->
                    <h2>配置安装</h2>
                    <p>请输入您的数据库连接信息和管理员账号信息：</p>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <!-- 数据库配置 -->
                        <div class="form-section">
                            <h3>数据库配置</h3>
                            <div class="form-group">
                                <label for="host">数据库主机</label>
                                <input type="text" id="host" name="host" value="localhost" required placeholder="例如：localhost 或 127.0.0.1">
                            </div>
                            <div class="form-group">
                                <label for="port">数据库端口</label>
                                <input type="number" id="port" name="port" value="3306" required placeholder="默认：3306">
                            </div>
                            <div class="form-group">
                                <label for="username">数据库用户名</label>
                                <input type="text" id="username" name="username" value="" required placeholder="例如：root">
                            </div>
                            <div class="form-group">
                                <label for="password">数据库密码</label>
                                <input type="password" id="password" name="password" value="" placeholder="如果没有密码，请留空">
                            </div>
                            <div class="form-group">
                                <label for="database">数据库名</label>
                                <input type="text" id="database" name="database" value="" required placeholder="例如：blogkit">
                            </div>
                            <div class="form-group">
                                <label for="prefix">表前缀</label>
                                <input type="text" id="prefix" name="prefix" value="bk_" required placeholder="例如：bk_">
                            </div>
                            <div class="form-group">
                                <label for="charset">字符集</label>
                                <input type="text" id="charset" name="charset" value="utf8mb4" required placeholder="默认：utf8mb4">
                            </div>
                        </div>
                        
                        <!-- 管理员配置 -->
                        <div class="form-section">
                            <h3>管理员账号配置</h3>
                            <div class="form-group">
                                <label for="admin_username">管理员用户名</label>
                                <input type="text" id="admin_username" name="admin_username" value="" required placeholder="请设置管理员用户名">
                            </div>
                            <div class="form-group">
                                <label for="admin_password">管理员密码</label>
                                <div style="position: relative;">
                                    <input type="password" id="admin_password" name="admin_password" value="" required placeholder="请设置管理员密码（至少8位）" style="padding-right: 100px;">
                                    <button type="button" onclick="togglePassword('admin_password')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #007bff; cursor: pointer; font-size: 14px;">
                                        显示密码
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="admin_email">管理员邮箱</label>
                                <input type="email" id="admin_email" name="admin_email" value="" required placeholder="请设置管理员邮箱">
                            </div>
                        </div>
                        
                        <?php if ($installProgress > 0): ?>
                            <div class="install-progress">
                                <h3>安装进度</h3>
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill">
                                        <?php echo $installProgress; ?>%
                                    </div>
                                </div>
                                <p>正在执行数据库安装，请稍候...</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="buttons">
                            <a href="install.php?step=2" class="btn btn-secondary">上一步</a>
                            <button type="submit" class="btn btn-primary">开始安装</button>
                        </div>
                    </form>
                    <?php break; ?>
                    
            <?php case 4: ?>
                    <?php
                    // 安装完成后自毁安装脚本，防止被恶意利用
                    if (file_exists(INSTALL_LOCK)) {
                        @rename(__FILE__, __FILE__ . '.bak');
                    }
                    ?>
                    <!-- 步骤4：安装完成 -->
                    <div class="completion">
                        <div class="completion-icon">✓</div>
                        <h2>安装成功！</h2>
                        <p>BlogKit 已经成功安装到您的服务器上。</p>
                        
                        <?php
                        // 从session读取管理员信息（安装锁已改为仅存时间戳）
                        $adminInfo = [
                            'username' => $_SESSION['install_admin_username'] ?? 'admin',
                            'email' => $_SESSION['install_admin_email'] ?? 'admin@example.com',
                        ];
                        ?>
                        
                        <div class="admin-info">
                            <h3>管理员账号信息：</h3>
                            <ul>
                                <li><strong>用户名：</strong> <?php echo htmlspecialchars($adminInfo['username']); ?></li>
                                <li><strong>密码：</strong> <?php echo htmlspecialchars($_SESSION['install_admin_password'] ?? '********'); ?></li>
                                <li><strong>邮箱：</strong> <?php echo htmlspecialchars($adminInfo['email']); ?></li>
                                <li><strong>提示：</strong> 请登录后及时修改密码，确保账户安全</li>
                            </ul>
                        </div>
                        <?php
                        // 清除session中的敏感密码
                        unset($_SESSION['install_admin_password']);
                        ?>
                        
                        <div class="security-tips">
                            <h3>安全提示：</h3>
                            <ul>
                                <li>请确保安装过程中生成的配置文件（core/config/database.php）具有适当的权限设置</li>
                                <li>请及时更新管理员密码，确保账户安全</li>
                                <li>定期备份数据库和重要文件</li>
                                <li>考虑删除 install.php 文件或设置其不可访问，进一步加强安全</li>
                                <li>保持系统和依赖库的更新，修复安全漏洞</li>
                            </ul>
                        </div>
                        
                        <div class="completion-buttons">
                            <a href="index.php" class="btn btn-success">访问首页</a>
                            <a href="admin.php" class="btn btn-primary">进入后台</a>
                        </div>
                    </div>
                    <?php break; ?>
            <?php endswitch; ?>
        </div>
    </div>
    
    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '隐藏密码';
            } else {
                input.type = 'password';
                button.textContent = '显示密码';
            }
        }
    </script>
</body>
</html>
