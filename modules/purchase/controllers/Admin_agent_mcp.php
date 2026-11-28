<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Admin_agent_mcp extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('purchase/admin_agent_model');
    }

    public function index($token = '')
    {
        header('Cache-Control: no-store'); header('Content-Type: application/json');
        $expected = (string) get_option('admin_agent_mcp_token');
        if (strlen($expected) < 32 || !hash_equals($expected, (string) $token)) { $this->_send(404, ['error' => 'Not found']); }
        if ($this->input->server('REQUEST_METHOD') !== 'POST') { $this->_send(405, ['error' => 'Method not allowed']); }
        $body = json_decode(file_get_contents('php://input'));
        if (!($body instanceof stdClass)) { $this->_send(400, ['error' => 'Invalid request']); }
        $id = $body->id ?? null; $method = $body->method ?? ''; $params = isset($body->params) && $body->params instanceof stdClass ? (array) $body->params : [];
        if (!array_key_exists('id', (array) $body)) return;
        if ($method === 'initialize') return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>['protocolVersion'=>'2025-06-18','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'kokonuts-admin-agent','version'=>'0.1.0'],'instructions'=>'Administrative configuration tools. Propose first; approve explicitly before creating accounts.']]);
        if ($method === 'ping') return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>new stdClass()]);
        if ($method === 'tools/list') return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>['tools'=>$this->_tools()]]);
        if ($method !== 'tools/call') return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>-32601,'message'=>'Method not found']]);
        $args = isset($params['arguments']) && $params['arguments'] instanceof stdClass ? (array) $params['arguments'] : [];
        $name = (string) ($params['name'] ?? '');
        if (!in_array($name, array_column($this->_tools(), 'name'), true)) {
            return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>json_encode(['error'=>'Unknown tool'])]],'isError'=>true]]);
        }
        // Same implementation as the purchase-agent and unified connectors,
        // so the three endpoints cannot drift apart.
        $this->load->model('purchase/purchase_agent_model');
        $bridge = (new ReflectionClass(Purchase_agent_mcp::class))->newInstanceWithoutConstructor();
        $bridge->configure_unified_bridge($this->purchase_agent_model, $this->admin_agent_model);
        return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>$bridge->call_tool_for_unified_mcp($name, $args)]);
    }

    const ADMIN_TOOLS = [
        'find_accounts', 'propose_account', 'approve_account_proposal',
        'find_categories', 'propose_category', 'approve_category_proposal',
        'propose_category_edit', 'approve_category_edit_proposal',
        'propose_item_with_mapping', 'propose_item_mapping_fix', 'approve_item_proposal',
        'list_pending_proposals', 'reject_proposal',
    ];

    private function _tools(): array
    {
        require_once __DIR__ . '/Purchase_agent_mcp.php';
        return array_values(array_filter(Purchase_agent_mcp::tool_definitions(), function ($tool) {
            return in_array($tool['name'], self::ADMIN_TOOLS, true);
        }));
    }

    private function _send(int $status, array $payload): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
}
