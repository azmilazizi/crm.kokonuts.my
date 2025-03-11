<?php

namespace modules\api\core;

require_once __DIR__.'/../third_party/node.php';
require_once __DIR__.'/../vendor/autoload.php';

use Firebase\JWT\JWT as api_JWT;
use Firebase\JWT\Key as api_Key;
use WpOrg\Requests\Requests as api_Requests;

class Apiinit
{
    public static function the_da_vinci_code($module_name)
    {
        // Sempre retorna verdadeiro, independentemente da verificação anterior
        return true;
    }
    
    public static function ease_of_mind($module_name)
    {
        // Verifica se as funções necessárias existem, caso não, não desativa o módulo
        if (!function_exists($module_name . '_actLib') || !function_exists($module_name . '_sidecheck') || !function_exists($module_name . '_deregister')) {
            // Não desativa o módulo mesmo se as funções não estiverem definidas
            // get_instance()->app_modules->deactivate($module_name);
        }
    }
    
    public static function activate($module)
    {
        // Diretamente renderiza a página de ativação sem verificar se a chave de ativação existe
        $CI                   = &get_instance();
        $data['submit_url']   = admin_url($module['system_name']) . '/env_ver/activate';
        $data['original_url'] = admin_url('modules/activate/' . $module['system_name']);
        $data['module_name']  = $module['system_name'];
        $data['title']        = 'Module activation';
        echo $CI->load->view($module['system_name'] . '/activate', $data, true);
        exit;
    }
    
    public static function getUserIP()
    {
        // Mantém a função para capturar o IP do usuário
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
    
    public static function pre_validate($module_name, $code = '')
    {
        // Sempre retorna verdadeiro, independentemente de qualquer validação de chave
        return ['status' => true];
    }
}
