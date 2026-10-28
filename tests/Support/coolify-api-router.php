<?php

// Local HTTP boundary for the deployment script; never contacts a real instance.
$log = getenv('REQUEST_LOG');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents($log, json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $path,
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'body' => json_decode(file_get_contents('php://input'), true),
])."\n", FILE_APPEND);

header('Content-Type: application/json');
$failurePath = dirname($log).'/failure-path';
if (file_exists($failurePath) && file_get_contents($failurePath) === $path) {
    http_response_code(422);
    echo json_encode(['message' => 'private-response']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && str_contains($path, '/deployments/')) {
    $statusFile = dirname($log).'/deployment-status';
    echo json_encode(['status' => file_exists($statusFile) ? file_get_contents($statusFile) : 'finished']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $topologyFile = dirname($log).'/topology';
    $topology = file_exists($topologyFile) ? file_get_contents($topologyFile) : '';
    $web = str_ends_with($path, '/web-test');
    echo json_encode([
        'build_pack' => $web ? 'dockerimage' : 'dockercompose',
        'environment_id' => $web && $topology === 'wrong-environment' ? 4 : 3,
        'docker_compose_location' => $topology === 'full-compose' ? '/compose.production.yaml' : '/compose.production-support.yaml',
        'health_check_enabled' => $topology !== 'unhealthy-web-config',
        'health_check_path' => '/ready',
    ]);
} else {
    $body = json_decode(file_get_contents('php://input'), true);
    $uuid = $body['uuid'] ?? 'application-test';
    echo json_encode(['deployments' => [[
        'resource_uuid' => $uuid,
        'deployment_uuid' => $uuid === 'web-test' ? 'web-deployment' : 'support-deployment',
    ]]]);
}
