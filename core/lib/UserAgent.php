<?php
/**
 * BlogKit - 轻量开源博客系统 (Lightweight Open-Source Blogging System)
 *
 * 版权所有 (C) 2026 石林波 (Bana)，保留所有权利。
 * Copyright (C) 2026 Shi Linbo (Bana). All rights reserved.
 *
 * 项目主页：https://www.blogkit.cn
 * 源码仓库：https://github.com/Bana/blogkit （主仓库）
 *           https://gitee.com/Bana/blogkit （镜像仓库）
 * 社区反馈：https://www.blogkit.cn/community
 *
 * 本程序为自由软件，依据 GNU General Public License v3.0 (GPLv3) 授权发布：
 * 您可依据协议自由使用、修改与再分发，但依据 GPLv3 第 4 条，
 * 分发时须保留本版权声明与许可声明，并随附协议全文；
 * 本程序不提供任何担保。协议全文：https://www.gnu.org/licenses/gpl-3.0.html
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */


class UserAgent {
    
    private static $browsers = [
        'Edge' => 'Edge',
        'Chrome' => 'Chrome',
        'Firefox' => 'Firefox',
        'Safari' => 'Safari',
        'Opera' => 'Opera',
        'MSIE' => 'Internet Explorer',
        'Trident' => 'Internet Explorer'
    ];
    
    private static $osList = [
        'Windows 10' => 'Windows 10',
        'Windows NT 6.3' => 'Windows 8.1',
        'Windows NT 6.2' => 'Windows 8',
        'Windows NT 6.1' => 'Windows 7',
        'Windows NT 6.0' => 'Windows Vista',
        'Windows NT 5.1' => 'Windows XP',
        'Windows NT 5.0' => 'Windows 2000',
        'Macintosh' => 'macOS',
        'iPad' => 'iPad',
        'iPhone' => 'iPhone',
        'iPod' => 'iPod',
        'Android' => 'Android',
        'Linux' => 'Linux'
    ];
    
    private static $mobileDevices = [
        'iPhone', 'iPad', 'iPod', 'Android', 'BlackBerry', 'Mobile', 'Opera Mini', 'IEMobile', 'Kindle', 'NetFront', 'Nokia', 'webOS', 'Palm', 'Symbian', 'Fennec', 'Maemo', 'MeeGo', 'Tizen', 'Bada'
    ];
    
    private static $tabletDevices = [
        'iPad', 'Android.*Mobile Safari', 'Tablet', 'Kindle', 'PlayBook', 'Transformer', 'Transformer Prime'
    ];
    
    public static function parse($userAgent) {
        $result = [
            'browser' => 'Unknown',
            'browser_version' => 'Unknown',
            'os' => 'Unknown',
            'device_type' => 'PC'
        ];
        
        $result['browser'] = self::getBrowser($userAgent);
        $result['browser_version'] = self::getBrowserVersion($userAgent, $result['browser']);
        $result['os'] = self::getOS($userAgent);
        $result['device_type'] = self::getDeviceType($userAgent);
        
        return $result;
    }
    
    private static function getBrowser($userAgent) {
        foreach (self::$browsers as $key => $name) {
            if (strpos($userAgent, $key) !== false) {
                return $name;
            }
        }
        return 'Unknown';
    }
    
    private static function getBrowserVersion($userAgent, $browser) {
        $version = 'Unknown';
        
        switch ($browser) {
            case 'Edge':
                if (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
            case 'Chrome':
                if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
            case 'Firefox':
                if (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
            case 'Safari':
                if (preg_match('/Version\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
            case 'Opera':
                if (preg_match('/Opera\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                } elseif (preg_match('/OPR\/([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
            case 'Internet Explorer':
                if (preg_match('/MSIE ([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                } elseif (preg_match('/Trident\/.*rv:([0-9.]+)/', $userAgent, $matches)) {
                    $version = $matches[1];
                }
                break;
        }
        
        return $version;
    }
    
    private static function getOS($userAgent) {
        foreach (self::$osList as $key => $name) {
            if (strpos($userAgent, $key) !== false) {
                if ($key === 'Macintosh') {
                    if (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
                        return 'macOS ' . str_replace('_', '.', $matches[1]);
                    }
                    return 'macOS';
                }
                if ($key === 'iPhone' || $key === 'iPad' || $key === 'iPod') {
                    if (preg_match('/OS ([0-9_]+)/', $userAgent, $matches)) {
                        return 'iOS ' . str_replace('_', '.', $matches[1]);
                    }
                    return 'iOS';
                }
                if ($key === 'Android') {
                    if (preg_match('/Android ([0-9.]+)/', $userAgent, $matches)) {
                        return 'Android ' . $matches[1];
                    }
                    return 'Android';
                }
                return $name;
            }
        }
        return 'Unknown';
    }
    
    private static function getDeviceType($userAgent) {
        foreach (self::$tabletDevices as $device) {
            if (strpos($userAgent, $device) !== false) {
                return 'Tablet';
            }
        }
        
        foreach (self::$mobileDevices as $device) {
            if (strpos($userAgent, $device) !== false) {
                return 'Mobile';
            }
        }
        
        return 'PC';
    }
    
    public static function getIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
        
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }
    
    public static function getReferer() {
        return $_SERVER['HTTP_REFERER'] ?? 'Direct';
    }
}
