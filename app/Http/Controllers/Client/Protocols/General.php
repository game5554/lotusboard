<?php

namespace App\Http\Controllers\Client\Protocols;


use App\Utils\Helper;

class General
{
    public $flag = 'general';
    private $servers;
    private $user;

    public function __construct($user, $servers)
    {
        $this->user = $user;
        $this->servers = $servers;
    }

    public function handle()
    {
        $servers = $this->servers;
        $user = $this->user;
        $uri = '';

        foreach ($servers as $item) {
            if ($item['type'] === 'vmess') {
                $uri .= self::buildVmess($user['uuid'], $item);
            }
            if ($item['type'] === 'shadowsocks') {
                $uri .= self::buildShadowsocks($user['uuid'], $item);
            }
            if ($item['type'] === 'trojan') {
                $uri .= self::buildTrojan($user['uuid'], $item);
            }
        }
        return base64_encode($uri);
    }

    public static function buildShadowsocks($password, $server)
    {
        if ($server['cipher'] === '2022-blake3-aes-128-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 16);
            $userKey = Helper::uuidToBase64($password, 16);
            $password = "{$serverKey}:{$userKey}";
        }
        if ($server['cipher'] === '2022-blake3-aes-256-gcm') {
            $serverKey = Helper::getServerKey($server['created_at'], 32);
            $userKey = Helper::uuidToBase64($password, 32);
            $password = "{$serverKey}:{$userKey}";
        }
        $name = rawurlencode($server['name']);
        $str = str_replace(
            ['+', '/', '='],
            ['-', '_', ''],
            base64_encode("{$server['cipher']}:{$password}")
        );
        return "ss://{$str}@{$server['host']}:{$server['port']}#{$name}\r\n";
    }

    public static function buildVmess($uuid, $server)
    {
        $config = [
            "v" => "2",
            "ps" => $server['name'],
            "add" => $server['host'],
            "port" => (string)$server['port'],
            "id" => $uuid,
            "aid" => '0',
            "net" => $server['network'],
            "type" => "none",
            "host" => "",
            "path" => "",
            "tls" => $server['tls'] ? "tls" : "",
        ];
        if ($server['tls']) {
            $config['fp'] = 'randomized';
            $config['scy'] = 'zero';
            if ($server['tlsSettings']) {
                $tlsSettings = $server['tlsSettings'];
                if (isset($tlsSettings['serverName']) && !empty($tlsSettings['serverName']))
                    $config['sni'] = $tlsSettings['serverName'];
            }
        }
        if ((string)$server['network'] === 'tcp') {
            $tcpSettings = $server['networkSettings'];
            if (isset($tcpSettings['header']['type'])) $config['type'] = $tcpSettings['header']['type'];
            if (isset($tcpSettings['header']['request']['path'][0])) $config['path'] = $tcpSettings['header']['request']['path'][0];
        }
        if ((string)$server['network'] === 'ws') {
            $wsSettings = $server['networkSettings'];
            if (isset($wsSettings['path'])) $config['path'] = "${wsSettings['path']}?ed=4096";
            if (isset($wsSettings['headers']['Host'])) $config['host'] = $wsSettings['headers']['Host'];
        }
        if ((string)$server['network'] === 'grpc') {
            $grpcSettings = $server['networkSettings'];
            if (isset($grpcSettings['serviceName'])) $config['path'] = $grpcSettings['serviceName'];
        }
        return "vmess://" . base64_encode(json_encode($config)) . "\r\n";
    }

    public static function buildTrojan($password, $server)
    {
        $name = rawurlencode($server['name']);
        $query = http_build_query([
            'allowInsecure' => $server['allow_insecure'],
            'peer' => $server['server_name'],
            'sni' => $server['server_name']
        ]);
        $uri = "trojan://{$password}@{$server['host']}:{$server['port']}?{$query}#{$name}";
        $uri .= "\r\n";
        return $uri;
    }

}