<?php
/**
 * ============================================
 * 全局辅助函数库（core/lib/functions.php）
 * ============================================
 * 集中存放跨控制器复用的全局辅助函数，由 bootstrap.php 统一加载。
 *
 * 背景：此前各后台控制器在处理结果时大量使用
 *   echo "<script>alert('...');window.location.href='...';</script>";
 * 输出原生浏览器弹窗（阻塞式、样式不统一、且单引号插值存在破坏脚本的风险）。
 * 该函数作为统一替代出口：输出一个最小化 HTML 页，
 * 复用后台 common.css / common.js 的统一 Toast（showToast）展示消息，
 * 展示指定时长后自动跳转（或返回上一页）。
 */

if (!function_exists('respondWithToast')) {
    /**
     * 输出统一 Toast 提示页并按需跳转（替代原生 alert 弹窗）
     *
     * @param string      $message 提示消息文本（自动做 HTML/JS 转义，可安全传入含引号内容）
     * @param string|null $redirect 跳转地址；传 'back' 表示返回上一页；传 null 表示仅提示不跳转
     * @param string|null $type    Toast 类型（success/error/warning/info）；
     *                             传 null 时按消息内容自动判断：含"成功"为 success，
     *                             命中失败类关键词为 error，其余为 info
     * @param int         $delay   停留时长（毫秒），到期后执行跳转
     * @return void
     */
    function respondWithToast($message, $redirect = null, $type = null, $delay = 1500) {
        // 未显式指定类型时按消息语义自动判断（成功绿 / 失败红 / 常规蓝）
        if ($type === null) {
            if (mb_strpos($message, '成功') !== false) {
                $type = 'success';
            } elseif (preg_match('/失败|错误|无法|不能|已存在|不存在|重复|非法|为空|不匹配/', $message)) {
                $type = 'error';
            } else {
                $type = 'info';
            }
        }

        // 后台静态资源地址：正常后台请求经 admin.php 入口已定义该常量，兜底防止直接路由场景
        $assetsUrl = defined('ADMIN_ASSETS_URL') ? ADMIN_ASSETS_URL : '/admin/assets';

        // json_encode 生成安全的 JS 字面量（含引号/斜杠/中文均不会破坏脚本结构）
        $messageJs = json_encode($message, JSON_UNESCAPED_UNICODE);
        $typeJs    = json_encode($type);
        $delayJs   = json_encode((int)$delay);

        // 组装跳转脚本：back => history.back()；指定地址 => 延时跳转；null => 仅提示
        if ($redirect === 'back') {
            $redirectJs = "window.history.back();";
        } elseif ($redirect !== null && $redirect !== '') {
            $redirectJs = 'window.location.href = ' . json_encode($redirect) . ';';
        } else {
            $redirectJs = '';
        }

        // 最小化提示页：仅引入后台公共样式与脚本，展示 Toast 后按需跳转
        echo '<!DOCTYPE html><html><head><meta charset="utf-8">';
        echo '<link rel="stylesheet" href="' . $assetsUrl . '/css/common.css">';
        echo '</head><body>';
        echo '<script src="' . $assetsUrl . '/js/common.js"></script>';
        echo '<script>';
        echo 'showToast(' . $messageJs . ', ' . $typeJs . ', ' . $delayJs . ');';
        if ($redirectJs !== '') {
            // 提示页无页面内容，Toast 停留时长即为阅读时间，无需额外延迟
            echo 'setTimeout(function(){ ' . $redirectJs . ' }, ' . $delayJs . ');';
        }
        echo '</script>';
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('respondFlash')) {
    /**
     * 写入闪存消息后直接重定向（PRG 模式，替代中间提示页）
     *
     * 与 respondWithToast 的区别：不在当前请求输出任何页面内容，
     * 而是把消息写入 $_SESSION['flash']，由目标页面渲染时通过
     * components/flash_messages.html 的 flash_render() 以统一 Toast 弹出。
     * 适用于列表页行内操作（删除/启用/禁用等）：点击后无中间页，
     * 直接回到列表并原地弹出 Toast。
     *
     * 注意：抽屉（openEntityDrawer）内的表单保存流程不要使用本函数——
     * 抽屉依赖 respondWithToast 的中间提示页在 iframe 内展示成功消息，
     * 改用闪存会导致提示在抽屉关闭时丢失。
     *
     * @param bool        $success 是否成功（决定 Toast 的 success/error 类型）
     * @param string      $message 提示消息文本
     * @param string|null $redirect 重定向地址；传 'back' 时回退到来源页（HTTP_REFERER），
     *                              传 null 则只写入消息不跳转
     * @return void
     */
    function respondFlash($success, $message, $redirect = null) {
        // 写入闪存消息（flash_messages.html 统一收集并以 Toast 渲染）
        $_SESSION['flash'][$success ? 'success' : 'error'] = $message;

        // 'back' 语义：服务端没有 history，回退到 HTTP_REFERER；
        // 无来源页时保持当前 URL 不动（仅提示）
        if ($redirect === 'back') {
            $redirect = $_SERVER['HTTP_REFERER'] ?? null;
        }
        if ($redirect !== null && $redirect !== '') {
            header('Location: ' . $redirect);
        }
        exit;
    }
}

/**
 * 生成后台静态资源地址（自动附加文件修改时间作为版本参数）
 *
 * 作用：CSS/JS 文件更新后地址随之变化，强制浏览器拉取新文件，
 * 避免用户端因强缓存继续使用旧样式/脚本（发布更新后无需手动清缓存）。
 *
 * @param string $path 以站点根为基准的绝对路径（如 /admin/assets/css/common.css）
 * @return string 带版本参数的资源地址
 */
function admin_asset_url($path) {
    $file = ROOT_PATH . $path;
    $ver = is_file($file) ? filemtime($file) : 0;
    return $path . '?v=' . $ver;
}
