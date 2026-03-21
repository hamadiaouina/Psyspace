<?php
declare(strict_types=1);

/**
 * Rate limit simple (anti-flood) par IP et route.
 * Stockage en fichiers dans /tmp du conteneur.
 */

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path) || $path === '') $path = '/';

function rl_key(string $ip, string $bucket): string {
    return hash('sha256', $ip . '|' . $bucket);
}

function rl_check(string $key, int $limit, int $windowSeconds): array {
    $file = sys_get_temp_dir() . '/rl_' . $key . '.json';
    $now = time();

    $data = ['start' => $now, 'count' => 0];

    if (is_file($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw ?: '', true);
        if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
            $data['start'] = (int)$decoded['start'];
            $data['count'] = (int)$decoded['count'];
        }
    }

    // reset fenêtre
    if (($now - (int)$data['start']) >= $windowSeconds) {
        $data = ['start' => $now, 'count' => 0];
    }

    $data['count'] = (int)$data['count'] + 1;
    file_put_contents($file, json_encode($data));

    $remaining = max(0, $limit - (int)$data['count']);
    $retryAfter = max(0, $windowSeconds - ($now - (int)$data['start']));
    $blocked = ((int)$data['count'] > $limit);

    return [$blocked, $remaining, $retryAfter, (int)$data['count']];
}

// règles
$rules = [
    ['bucket' => 'global', 'limit' => 120, 'window' => 60],
];

// règle plus stricte sur login_action.php
$endsWithLogin = (substr($path, -strlen('/login_action.php')) === '/login_action.php');
if ($endsWithLogin) {
    $rules[] = ['bucket' => 'login', 'limit' => 20, 'window' => 60];
}

foreach ($rules as $r) {
    $key = rl_key($ip, (string)$r['bucket']);
    [$blocked, $remaining, $retryAfter, $count] = rl_check($key, (int)$r['limit'], (int)$r['window']);

    header('X-RateLimit-Limit: ' . $r['limit']);
    header('X-RateLimit-Remaining: ' . $remaining);

    if ($blocked) {
        header('Retry-After: ' . $retryAfter);
        http_response_code(429);
        error_log("RATE_LIMIT block ip={$ip} bucket={$r['bucket']} path={$path} count={$count}");
        echo "Too many requests. Retry after {$retryAfter}s.";
        exit;
    }
}