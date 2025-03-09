<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * load error page
 * @param  string $title  
 * @param  string $content
 * @return view         
 */
function infor_page($title = '',$content = '',$previous_link=''){
      $data['title'] = $title;
      $data['content'] = $content;  
      $data['previous_link'] = $previous_link;  
      $CI = & get_instance();                  
      $CI->data($data);
      $CI->view('client/info_page');
      $CI->layout();
}

/**
 * get all email contacts
 * @return $data_email
 */
function get_all_email_contacts(){
	$CI = & get_instance();                  
	$data = $CI->db->get(db_prefix() . 'contacts')->result_array();
	$data_email = [];
	foreach ($data as $key => $value) {
		$data_email[] = $value['email'];
	}
	return $data_email;
}
/**
 * cron job sync woo
 * @param  string $type    
 * @param  int $store   
 * @param  int $minutes 
 * @return  bolean         
 */
function cron_job_sync_woo($store = ''){

	$CI = & get_instance();      

    $CI->load->model('omni_sales/omni_sales_model');
    $CI->load->library('omni_sales/asynclibrary');
	$hour = time();
	$hour_cron = get_option('time_cron_woo');
    //records
    $records_time1 = get_option('records_time1');
	$records_time2 = get_option('records_time2');
	$records_time3 = get_option('records_time3');
	$records_time4 = get_option('records_time4');
	$records_time5 = get_option('records_time5');
	$records_time6 = get_option('records_time6');
	$records_time7 = get_option('records_time7');
	$records_time8 = get_option('records_time8');

	$config_store = $CI->omni_sales_model->get_setting_auto_sync_store($store);

	$sync_omni_sales_inventorys = $config_store[0]['sync_omni_sales_inventorys'];
	$sync_omni_sales_products = $config_store[0]['sync_omni_sales_products'];
	$sync_omni_sales_orders = $config_store[0]['sync_omni_sales_orders'];
	$sync_omni_sales_description = $config_store[0]['sync_omni_sales_description'];
	$sync_omni_sales_images = $config_store[0]['sync_omni_sales_images'];
	$price_crm_woo = $config_store[0]['price_crm_woo'];
	$product_info_enable_disable = $config_store[0]['product_info_enable_disable'];
	$product_info_image_enable_disable = $config_store[0]['product_info_image_enable_disable'];

	$minute_sync_inventory_info_time2 = $config_store[0]['time2'];
	$minute_sync_price_time3 = $config_store[0]['time3'];
	$minute_sync_decriptions_time4 = $config_store[0]['time4'];
	$minute_sync_images_time5 = $config_store[0]['time5'];
	$minute_sync_orders_time6 = $config_store[0]['time6'];
	$minute_sync_product_info_time7 = $config_store[0]['time7'];
	$minute_sync_product_info_images_time8 = $config_store[0]['time8'];
		if($store != ''){
	    	if($sync_omni_sales_inventorys == "1"){
	    			if($hour >= strtotime($records_time2)){
	    				
					$result = $CI->omni_sales_model->process_inventory_synchronization_detail($store);
	            	$records_time2 = strtotime($records_time2);
					$run_time2 = date("H:i:s", strtotime('+'.$minute_sync_inventory_info_time2.' minutes', $records_time2));
					update_option('records_time2', $run_time2);
					
	    			}
	    	}
	    
	    	if($sync_omni_sales_description == "1"){
	    		if($hour >= strtotime($records_time4)){
	            	$CI->omni_sales_model->process_decriptions_synchronization_detail($store);
	            	$records_time4 = strtotime($records_time4);
					$run_time4 = date("H:i:s", strtotime('+'.$minute_sync_decriptions_time4.' minutes', $records_time4));
					update_option('records_time4', $run_time4);
	    		}
	    	}
	    	if($sync_omni_sales_images == "1"){
	    		if($hour >= strtotime($records_time5)){
	            	$CI->omni_sales_model->process_images_synchronization_detail($store);
	            	$records_time5 = strtotime($records_time5);
					$run_time5 = date("H:i:s", strtotime('+'.$minute_sync_images_time5.' minutes', $records_time5));
					update_option('records_time5', $run_time5);
	    		}
	    	}
	    	if($price_crm_woo == "1")
	    		if($hour >= strtotime($records_time3)){
	            	$CI->omni_sales_model->process_price_synchronization($store);
	            	$records_time3 = strtotime($records_time3);
					$run_time3 = date("H:i:s", strtotime('+'.$minute_sync_price_time3.' minutes', $records_time3));
					update_option('records_time3', $run_time3);
	    		}
	    	}
	    // 	if($product_info_enable_disable == "1"){
	    //         	$url = site_url()."omni_sales/omni_sales_client/sync_products_from_info_woo/".$store;
     //    			$success = $CI->asynclibrary->do_in_background($url, array());
	    //         	$records_time7 = strtotime($records_time7);
					// $run_time7 = date("H:i:s", strtotime('+'.$minute_sync_product_info_time7.' minutes', $records_time7));
					// update_option('records_time7', $run_time7);
	    // 	}

	    	if($product_info_image_enable_disable == "1"){
	    		if($hour >= strtotime($records_time8)){
        			$url = site_url()."omni_sales/omni_sales_client/sync_products_from_store/".$store;
        			$success = $CI->asynclibrary->do_in_background($url, array());
	            	$records_time8 = strtotime($records_time8);
					$run_time8 = date("H:i:s", strtotime('+'.$minute_sync_product_info_images_time8.' minutes', $records_time8));
					update_option('records_time8', $run_time8);
	    		}
	    	}
	    	
	    	if($sync_omni_sales_orders == "1"){
	    		if($hour >= strtotime($records_time6)){
	            	$CI->omni_sales_model->process_orders_woo($store);
	            	$records_time6 = strtotime($records_time6);
					$run_time6 = date("H:i:s", strtotime('+'.$minute_sync_orders_time6.' minutes', $records_time6));
					update_option('records_time6', $run_time6);
	    		}
	    	}
		
    return true;
}

/**
 * get all store 
 * @return  stores
 */
function get_all_store(){
	$CI = & get_instance();      
    $CI->load->model('omni_sales/omni_sales_model');
	return $CI->omni_sales_model->get_woocommere_store();
}

function get_name_store($id){
	$CI = & get_instance();      
    $CI->db->where('id', $id);
    return $CI->db->get(db_prefix().'omni_master_channel_woocommere')->row()->name_channel;
}
hooks()->add_action('after_email_templates', 'add_purchase_receipt_email_templates');

if (!function_exists('add_purchase_receipt_email_templates')) {
    /**
     * Init inventory email templates and assign languages
     * @return void
     */
    function add_purchase_receipt_email_templates()
    {
        $CI = &get_instance();

        $data['purchase_receipt_templates'] = $CI->emails_model->get(['type' => 'purchase_receipt', 'language' => 'english']);

        $CI->load->view('omni_sales/purchase_receipt_email_template', $data);
    }
}


/**
 * omni sales reformat currency j
 * @param  [type] $value 
 * @return [type]        
 */
function omni_sales_reformat_currency_j($value)
{

    $f_dot = str_replace(',','', $value);
    return ((float)$f_dot + 0);
}

/**
 * omni sales get payment name
 * @param  integer $id 
 * @return [type]     
 */
function omni_sales_get_payment_name($id)
{
	$CI = & get_instance(); 

	$payment_name ='';
	$CI->db->where('id',$id);               
	$data = $CI->db->get(db_prefix() . 'payment_modes')->row();

	if($data){
		$payment_name .= $data->name;
	}
	return $payment_name;
}

/**
 * omni sales get customer name
 * @param  [type] $id 
 * @return [type]     
 */
function omni_sales_get_customer_name($id, $name)
{
	$customer_name ='';

	$CI = & get_instance(); 

	if(isset($id) && $id != ''){
		$CI->db->where(db_prefix() . 'clients.userid', $id);
	    $client = $CI->db->get(db_prefix() . 'clients')->row();

		if($client){
			$customer_name .= $client->company;
		}
	}else{
		$customer_name .= $name;
	}

	return $customer_name;
}
