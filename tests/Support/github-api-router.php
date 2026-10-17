<?php

if (str_starts_with($_SERVER['REQUEST_URI'], '/api/v1/')) {
    require __DIR__.'/coolify-api-router.php';

    return;
}

$scenario = json_decode(file_get_contents(getenv('SCENARIO_FILE')), true);
$sha = str_repeat('b', 40);
$head = ['ref' => 'release-please--branches--main--components--dlp-friends', 'sha' => $sha, 'repo' => ['full_name' => 'zdossantos/dlp-friends']];
$pr = ['number' => 268, 'state' => 'open', 'draft' => false, 'head' => $head,
    'base' => ['ref' => 'main', 'repo' => ['full_name' => 'zdossantos/dlp-friends']],
    'user' => ['login' => 'zdossantos'], 'labels' => [['name' => 'autorelease: pending']]];
$pr = array_replace_recursive($pr, $scenario['pr'] ?? []);
header('Content-Type: application/json');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_contains($path, '/git/ref') && ! str_contains($path, '/git/commits/')) {
    $body = json_decode(file_get_contents('php://input'), true);
    file_put_contents(getenv('SCENARIO_FILE').'.refs', json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'path' => $path, 'body' => $body])."\n", FILE_APPEND);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(($scenario['existing_ref'] ?? false) ? 422 : 201);
        echo '{}';
    } else {
        echo json_encode(['object' => ['sha' => $scenario['ref_sha'] ?? $sha]]);
    }
} elseif (str_ends_with($path, '/pulls')) {
    echo json_encode(($scenario['no_pr'] ?? false) ? [] : [$pr]);
} elseif (str_ends_with($path, '/check-runs')) {
    $names = ['Conventional PR title', 'PHP quality', 'Backend tests', 'Frontend quality', 'Vite build', 'Docker build'];
    echo json_encode(['total_count' => 6, 'check_runs' => array_map(fn ($name) => [
        'id' => 123, 'name' => $name, 'head_sha' => $scenario['checks_sha'] ?? $sha,
        'status' => 'completed', 'conclusion' => $scenario['conclusion'] ?? 'success', 'app' => ['slug' => 'github-actions'],
    ], $names)]);
} else {
    echo json_encode(['sha' => $sha, 'tree' => ['sha' => str_repeat('c', 40)]]);
}
