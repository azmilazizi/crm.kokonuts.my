<?php defined('BASEPATH') or exit('No direct script access allowed');

// Path to your Firebase service account JSON file.
// Download from: Firebase Console → Project Settings → Service accounts
//                → Generate new private key
// Store the file outside the web root, e.g. alongside this config directory.
$config['fcm_service_account_path'] = APPPATH . 'config/fcm_service_account.json';

// Your Firebase Project ID — visible in Firebase Console → Project settings.
$config['fcm_project_id'] = 'kokonuts-manager';
