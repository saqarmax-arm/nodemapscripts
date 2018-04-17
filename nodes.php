<?php
use Symfony\Component\Yaml\Yaml;

include __DIR__ . '/../vendor/autoload.php';
$parameters = Yaml::parse(file_get_contents(__DIR__ . '/../app/config/parameters.yml'), false, false, true);

_log('start updating');
$time = date('Y-m-d H:i');
$redis = new \Redis();
$redis->connect($parameters->parameters->redis_host ?? '127.0.0.1', $parameters->parameters->redis_port ?? 6379);
$redis->auth($parameters->parameters->redis_password ?? '');
$redis->select(3);
$cache = new \Doctrine\Common\Cache\RedisCache();
$cache->setRedis($redis);
$cache->setNamespace('nodes_v3_');

$updatingKey = 'updating';
if ($cache->fetch($updatingKey) !== false) {
    _log('another process is updating...');
    exit;
}
$cache->save($updatingKey, true);

_log('start fetching ips');
$files = glob('/data/qtumpeerinfo*.json');
$uniqueIps = [];
foreach ($files as $file) {
    $nodes = json_decode(file_get_contents($file));
    if ($nodes === false) {
        continue;
    }
    foreach ($nodes as $node) {
        $addr = $node->addr;
        if (preg_match('|^((?:\d+\.){3}\d+):\d+$|', $addr, $matches)) {
            $ip = $matches[1];
            $uniqueIps[$ip] = 1;
        }
    }
}
_log('end fetching ips', count($uniqueIps));

_log('start comparing and fetching coordinates');
$savedIps = (array)$cache->fetch('nodes');
$ips = array_intersect_key($uniqueIps, $savedIps);
$newIps = array_diff_key($uniqueIps, $savedIps);
$unknownIps = [];
_log('newIps: ' . count($newIps));
foreach ($newIps as $ip=>$count) {
    $data = getIpData($ip);
    if ($data !== false) {
        _log('success', $ip);
        $cache->save($ip, $data);
        $ips[$ip] = $count;
    } else {
        _log('failed', $ip);
        $unknownIps[$ip] = $ip;
    }
}
_log('end comparing and fetching coordinates', 'unknownIps: ' . count($unknownIps));

$cache->save('nodes', $ips);
$cache->save('unknown_nodes', array_values($unknownIps));

$historyKey = 'history';
$history = $cache->fetch($historyKey);
if ($history === false) {
    $history = [];
}
$history[] = $time;
$cache->save($historyKey, $history);
$cache->save($time, array_values($ips));
$cache->save($updatingKey, false);
_log('end updating', 'ips: ' . count($ips));

function _log() {
    echo sprintf("[%s] %s\n", date('Y-m-d H:i:s'), implode(" - ", array_map('json_encode', func_get_args())));
}

function getIpData($ip)
{
    try {
        $ch = curl_init('http://freegeoip.net/json/' . $ip);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $geoInfo = curl_exec($ch);
        curl_close($ch);
        if ($geoInfo) {
            $geoInfo = json_decode($geoInfo, true);
        }
        if ($geoInfo) {
            return $geoInfo;
        }
    } catch (Exception $e) {
    }
    return false;
}
