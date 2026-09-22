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

/**
 * IP白名单类
 * 提供IP地址验证、CIDR段验证、真实IP获取和白名单验证功能
 */
class IpWhitelist {
    /**
     * 可信代理IP列表
     * @var array
     */
    private static $trustedProxies = [];

    /**
     * 白名单IP列表
     * @var array
     */
    private static $whitelist = [];

    /**
     * 设置可信代理
     * @param array $proxies 可信代理IP或IP段数组
     */
    public static function setTrustedProxies($proxies) {
        self::$trustedProxies = is_array($proxies) ? $proxies : [];
    }

    /**
     * 添加可信代理
     * @param string $proxy 可信代理IP或IP段
     */
    public static function addTrustedProxy($proxy) {
        if (!in_array($proxy, self::$trustedProxies)) {
            self::$trustedProxies[] = $proxy;
        }
    }

    /**
     * 设置白名单
     * @param array $whitelist 白名单IP或IP段数组
     */
    public static function setWhitelist($whitelist) {
        self::$whitelist = is_array($whitelist) ? $whitelist : [];
    }

    /**
     * 添加到白名单
     * @param string $ipOrCidr IP地址或CIDR段
     */
    public static function addToWhitelist($ipOrCidr) {
        if (!in_array($ipOrCidr, self::$whitelist)) {
            self::$whitelist[] = $ipOrCidr;
        }
    }

    /**
     * 验证IPv4地址
     * @param string $ip IP地址
     * @return bool
     */
    public static function isValidIpv4($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * 验证IPv6地址
     * @param string $ip IP地址
     * @return bool
     */
    public static function isValidIpv6($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * 验证IP地址（IPv4或IPv6）
     * @param string $ip IP地址
     * @return bool
     */
    public static function isValidIp($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * 验证CIDR格式
     * @param string $cidr CIDR格式字符串
     * @return bool
     */
    public static function isValidCidr($cidr) {
        if (strpos($cidr, '/') === false) {
            return false;
        }
        list($ip, $prefix) = explode('/', $cidr, 2);
        if (!self::isValidIp($ip)) {
            return false;
        }
        if (!ctype_digit($prefix)) {
            return false;
        }
        $prefix = (int)$prefix;
        if (self::isValidIpv4($ip)) {
            return $prefix >= 0 && $prefix <= 32;
        }
        return $prefix >= 0 && $prefix <= 128;
    }

    /**
     * 检查IP是否在CIDR段内
     * @param string $ip IP地址
     * @param string $cidr CIDR格式字符串
     * @return bool
     */
    public static function ipInCidr($ip, $cidr) {
        if (!self::isValidIp($ip) || !self::isValidCidr($cidr)) {
            return false;
        }
        list($subnet, $prefix) = explode('/', $cidr, 2);
        $prefix = (int)$prefix;

        if (self::isValidIpv4($ip) && self::isValidIpv4($subnet)) {
            return self::ipv4InCidr($ip, $subnet, $prefix);
        } elseif (self::isValidIpv6($ip) && self::isValidIpv6($subnet)) {
            return self::ipv6InCidr($ip, $subnet, $prefix);
        }

        return false;
    }

    /**
     * 检查IPv4是否在CIDR段内
     * @param string $ip IPv4地址
     * @param string $subnet 子网IP
     * @param int $prefix 前缀长度
     * @return bool
     */
    private static function ipv4InCidr($ip, $subnet, $prefix) {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($prefix === 0) {
            return true;
        }
        $mask = ~((1 << (32 - $prefix)) - 1);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    /**
     * 检查IPv6是否在CIDR段内
     * @param string $ip IPv6地址
     * @param string $subnet 子网IP
     * @param int $prefix 前缀长度
     * @return bool
     */
    private static function ipv6InCidr($ip, $subnet, $prefix) {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        if ($prefix === 0) {
            return true;
        }
        $bytes = (int)ceil($prefix / 8);
        $remainder = $prefix % 8;
        for ($i = 0; $i < $bytes - 1; $i++) {
            if (ord($ipBin[$i]) !== ord($subnetBin[$i])) {
                return false;
            }
        }
        if ($remainder > 0) {
            $mask = ~((1 << (8 - $remainder)) - 1) & 0xFF;
            if ((ord($ipBin[$bytes - 1]) & $mask) !== (ord($subnetBin[$bytes - 1]) & $mask)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检查请求是否来自可信代理
     * @param string $remoteAddr REMOTE_ADDR
     * @return bool
     */
    private static function isTrustedProxy($remoteAddr) {
        if (empty(self::$trustedProxies)) {
            return true;
        }
        foreach (self::$trustedProxies as $proxy) {
            if (self::isValidCidr($proxy)) {
                if (self::ipInCidr($remoteAddr, $proxy)) {
                    return true;
                }
            } elseif ($remoteAddr === $proxy) {
                return true;
            }
        }
        return false;
    }

    /**
     * 获取客户端真实IP地址
     * @return string
     */
    public static function getRealIp() {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_CLIENT_IP'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    if (self::isValidIp($ip)) {
                        return $ip;
                    }
                }
            }
        }

        return $remoteAddr;
    }

    /**
     * 验证IP是否在白名单中
     * @param string|null $ip 要验证的IP地址，为空时自动获取
     * @return bool
     */
    public static function validate($ip = null) {
        if (empty(self::$whitelist)) {
            return true;
        }

        if ($ip === null) {
            $ip = self::getRealIp();
        }

        foreach (self::$whitelist as $item) {
            if (self::isValidCidr($item)) {
                if (self::ipInCidr($ip, $item)) {
                    return true;
                }
            } elseif (self::isValidIp($item)) {
                if ($ip === $item) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 清空白名单
     */
    public static function clearWhitelist() {
        self::$whitelist = [];
    }

    /**
     * 清空可信代理
     */
    public static function clearTrustedProxies() {
        self::$trustedProxies = [];
    }

    /**
     * 获取当前白名单
     * @return array
     */
    public static function getWhitelist() {
        return self::$whitelist;
    }

    /**
     * 获取当前可信代理
     * @return array
     */
    public static function getTrustedProxies() {
        return self::$trustedProxies;
    }
}
