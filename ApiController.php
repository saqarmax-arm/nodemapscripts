  /**
     * @Route("/api/nodes")
     */
    public function nodesAction()
    {
        $redis = new \Redis();
        $redis->connect($this->container->getParameter('redis_host'), $this->container->getParameter('redis_port'));
        $redis->auth($this->container->getParameter('redis_password'));
        $redis->select(3);
        $cache = new \Doctrine\Common\Cache\RedisCache();
        $cache->setRedis($redis);
        $cache->setNamespace('nodes_v3_');

        $root = $this->get('kernel')->getRootDir();
        $ips = $cache->fetch('nodes');
        $coordinates = $cache->fetchMultiple(array_keys((array)$ips));
        $nodes = [];
        foreach ($coordinates as $ip=>$data) {
            if ($data == false) {
                continue;
            }
            $key = $data['longitude'] . '_' . $data['latitude'];
            if (!isset($nodes[$key])) {
                $nodes[$key] = [
                    'city'=>$data['city'],
                    'province'=>$data['region_name'],
                    'country'=>$data['country_name'],
                    'country_code'=>$data['country_code'],
                    'count'=>0,
                    'longitude'=>$data['longitude'],
                    'latitude'=>$data['latitude'],
                ];
            }
            $nodes[$key]['count']++;
        }
        usort($nodes, function($a, $b) {
            return $a['count'] - $b['count'];
        });
        return new JsonResponse(array_values($nodes));
    }

    private function getIpData($ip)
    {
        try {
            $ch = curl_init('https://freegeoip.app/json/' . $ip);
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
}
