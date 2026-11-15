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
        try {
            if ($name === 'find_accounts') $value = $this->admin_agent_model->find_accounts((string)($args['query']??''), isset($args['parent_account_id'])?(int)$args['parent_account_id']:null, (int)($args['limit']??30));
            elseif ($name === 'propose_account') $value = $this->admin_agent_model->propose_account((int)($args['parent_account_id']??0), (string)($args['leaf_name']??''), isset($args['segment'])?(string)$args['segment']:null);
            elseif ($name === 'approve_account_proposal') $value = $this->admin_agent_model->approve_account_proposal((int)($args['proposal_id']??0));
            elseif ($name === 'find_categories') $value = $this->admin_agent_model->find_categories((string)($args['category_type']??'expense'), (string)($args['query']??''), (int)($args['limit']??30));
            elseif ($name === 'propose_category') $value = $this->admin_agent_model->propose_category((string)($args['category_type']??''), (string)($args['name']??''), isset($args['debit_account'])?(int)$args['debit_account']:null, isset($args['credit_account'])?(int)$args['credit_account']:null, (string)($args['description']??''));
            elseif ($name === 'approve_category_proposal') $value = $this->admin_agent_model->approve_category_proposal((int)($args['proposal_id']??0));
            else $value = ['error'=>'Unknown tool'];
        } catch (Throwable $e) { $value = ['error'=>$e->getMessage()]; }
        return $this->_send(200, ['jsonrpc'=>'2.0','id'=>$id,'result'=>['content'=>[['type'=>'text','text'=>json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]]]]);
    }

    private function _tools(): array
    {
        return [
            ['name'=>'find_accounts','description'=>'Find active chart-of-account accounts, optionally under a parent. Read-only.','inputSchema'=>['type'=>'object','properties'=>['query'=>['type'=>'string'],'parent_account_id'=>['type'=>'integer'],'limit'=>['type'=>'integer']]]],
            ['name'=>'propose_account','description'=>'Generate a new account name from the parent and sibling naming syntax. Does not create the account.','inputSchema'=>['type'=>'object','required'=>['parent_account_id','leaf_name'],'properties'=>['parent_account_id'=>['type'=>'integer'],'leaf_name'=>['type'=>'string'],'segment'=>['type'=>'string']]]],
            ['name'=>'approve_account_proposal','description'=>'Explicitly approve and create a previously proposed account.','inputSchema'=>['type'=>'object','required'=>['proposal_id'],'properties'=>['proposal_id'=>['type'=>'integer']]]],
            ['name'=>'find_categories','description'=>'Find expense or bill categories. Read-only.','inputSchema'=>['type'=>'object','required'=>['category_type'],'properties'=>['category_type'=>['type'=>'string','enum'=>['expense','bill']],'query'=>['type'=>'string'],'limit'=>['type'=>'integer']]]],
            ['name'=>'propose_category','description'=>'Propose an expense or bill category. Bill categories require validated debit and credit accounts. Does not create anything.','inputSchema'=>['type'=>'object','required'=>['category_type','name'],'properties'=>['category_type'=>['type'=>'string','enum'=>['expense','bill']],'name'=>['type'=>'string'],'debit_account'=>['type'=>'integer'],'credit_account'=>['type'=>'integer'],'description'=>['type'=>'string']]]],
            ['name'=>'approve_category_proposal','description'=>'Explicitly approve and create a previously proposed category.','inputSchema'=>['type'=>'object','required'=>['proposal_id'],'properties'=>['proposal_id'=>['type'=>'integer']]]],
        ];
    }

    private function _send(int $status, array $payload): void { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
}
