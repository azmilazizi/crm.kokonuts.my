<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Purchase_receipt_merge_fields extends App_merge_fields
{
    public function build()
    {
        return [
            [
                'name'      => 'Staff name',
                'key'       => '{staff_name}',
                'available' => [
                    'purchase_receipt',
                ],
            ],
            
            [
                'name'      => 'Notification content',
                'key'       => '{notification_content}',
                'available' => [
                    'purchase_receipt',
                ],
            ],
            
        ];
    }


    /**
     * Merge field for appointments
     * @param  mixed $teampassword 
     * @return array
     */
    public function format($notification_info)
    {
        $fields = [];

        if (!$notification_info) {
            return $fields;
        }

        $fields['{staff_name}']                  =   $notification_info->staff_name ;

        $fields['{notification_content}']                  =  $notification_info->notification_content ;

        return $fields;
    }


}
