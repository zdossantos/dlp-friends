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
} else {
    echo json_encode(['deployments' => [[
        'resource_uuid' => 'application-test',
        'deployment_uuid' => 'deployment-test',
    ]]]);
}
