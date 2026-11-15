<?php

defined('BASEPATH') or exit('No direct script access allowed');

$config['csrf_exclude_uris'][] = 'purchase/agent_mcp/*';
$config['csrf_exclude_uris'][] = 'purchase/admin_agent_mcp/*';
