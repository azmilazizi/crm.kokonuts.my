<?php

// Standalone protocol regression checks; no database or credentials required.
// Run: php tools/test-mcp.php
define('BASEPATH', __DIR__);

class App_Controller
{
    public $load;
    public $pos_ai_tools;
    public $input;
}

function db_prefix()
{
    return 'tbl';
}

require dirname(__DIR__) . '/application/controllers/Mcp.php';

// Exercise the real entry point in a child process because it terminates requests.
if (($argv[1] ?? '') === '--transport') {
    function get_option($name)
    {
        global $argv;
        return $argv[2];
    }
    register_shutdown_function(function () {
        fwrite(STDERR, (string) http_response_code());
    });
    $endpoint = new Mcp();
    $endpoint->input = new class {
        public function server($name)
        {
            global $argv;
            return $argv[4];
        }
    };
    $endpoint->index($argv[3]);
    exit;
}

$server = new Mcp();
$server->pos_ai_tools = new class {
    public function definitions()
    {
        return [['name' => 'get_sales_summary', 'description' => 'Sales',
            'parameters' => ['type' => 'object', 'properties' => []]]];
    }

    public function names()
    {
        return ['get_sales_summary'];
    }
};
$server->load = new class {
    public function library($name)
    {
    }
};

$checks = 0;
function check($condition, $message)
{
    global $checks;
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function callRpc($message)
{
    global $server;
    $method = new ReflectionMethod(Mcp::class, '_handle_message');
    return $method->invoke($server, json_decode($message));
}

$testToken = str_repeat('a', 64);
foreach ([['', '', 'POST', 404], ['short', 'short', 'POST', 404],
    [$testToken, str_repeat('b', 64), 'POST', 404], [$testToken, $testToken, 'GET', 405],
    [$testToken, $testToken, 'POST', 400]] as [$stored, $supplied, $verb, $expectedStatus]) {
    $process = proc_open([PHP_BINARY, __FILE__, '--transport', $stored, $supplied, $verb],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    fclose($pipes[0]);
    $payload = stream_get_contents($pipes[1]);
    $status = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    check(proc_close($process) === 0, 'Transport exits cleanly');
    check($status === (string) $expectedStatus, 'Token authentication and HTTP method handling');
    check(is_array(json_decode($payload, true)), 'Transport returns a JSON response');
}

foreach (Mcp::PROTOCOL_VERSIONS as $version) {
    $result = callRpc(json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize',
        'params' => ['protocolVersion' => $version]]));
    check($result['result']['protocolVersion'] === $version, 'Supported protocol negotiation');
    check(isset($result['result']['capabilities']['tools']), 'Advertise tools');
}
$result = callRpc('{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"future"}}');
check($result['result']['protocolVersion'] === Mcp::PROTOCOL_VERSIONS[0], 'Do not echo unsupported protocols');

foreach (['null', '42', '[]', '{}', '{"jsonrpc":"1.0","id":1,"method":"ping"}',
    '{"jsonrpc":"2.0","id":[],"method":"ping"}', '{"jsonrpc":"2.0","id":1,"method":[]}'] as $request) {
    check(callRpc($request)['error']['code'] === -32600, 'Reject malformed request: ' . $request);
}
foreach (['{"jsonrpc":"2.0","id":1,"method":"tools/call","params":[]}',
    '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":[]}}',
    '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"run_query","arguments":[]}}'] as $request) {
    check(callRpc($request)['error']['code'] === -32602, 'Reject malformed tool parameters');
}
check(callRpc('{"jsonrpc":"2.0","method":"notifications/initialized"}') === null, 'Do not answer notifications');
$result = callRpc('{"jsonrpc":"2.0","id":0,"method":"ping"}');
check($result['id'] === 0 && $result['result'] instanceof stdClass, 'Preserve zero IDs and empty objects');
check(callRpc('{"jsonrpc":"2.0","id":"x","method":"unknown"}')['error']['code'] === -32601, 'Unknown method');
$result = callRpc('{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"unknown","arguments":{}}}');
check($result['result']['isError'] === true, 'Unknown tools return tool errors');
$result = callRpc('{"jsonrpc":"2.0","id":1,"method":"tools/list"}');
foreach ($result['result']['tools'] as $tool) {
    check($tool['annotations']['readOnlyHint'] === true, 'Advertise read-only tools');
    check($tool['inputSchema']['type'] === 'object', 'Tool inputs use object schemas');
}

$validate = new ReflectionMethod(Mcp::class, '_validate_sql');
foreach (['DELETE FROM tblclients', 'SELECT password FROM tblstaff', 'SELECT * FROM tbloptions',
    'SELECT SLEEP(10)', 'SELECT 1; SELECT 2', 'SELECT 1 INTO OUTFILE "/tmp/export"'] as $sql) {
    check($validate->invoke($server, $sql) !== null, 'Reject unsafe SQL');
}
check($validate->invoke($server, 'SELECT company FROM tblclients LIMIT 10') === null, 'Allow read-only SQL');

foreach (['development', 'production'] as $environment) {
    $source = file_get_contents(dirname(__DIR__) . '/application/config/' . $environment . '/config.php');
    preg_match('/\$config\[\'csrf_exclude_uris\'\] = \[(.*?)\];/', $source, $assignment);
    preg_match_all("/'([^']+)'/", $assignment[1], $patterns);
    $excluded = function ($uri) use ($patterns) {
        foreach ($patterns[1] as $pattern) {
            if (preg_match('#^' . $pattern . '$#i', $uri)) {
                return true;
            }
        }
        return false;
    };
    check($excluded('mcp/' . str_repeat('a', 64)), 'MCP clients do not need a browser CSRF cookie');
    check(!$excluded('admin/settings'), 'Settings retain CSRF protection');
    check(!$excluded('mcp/short'), 'CSRF exemption is limited to connector paths');
    check(!$excluded('mcp/' . str_repeat('a', 64) . '/extra'), 'No nested route exemption');
}
echo "Passed {$checks} MCP checks.\n";
