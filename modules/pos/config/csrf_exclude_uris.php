<?php defined('BASEPATH') or exit('No direct script access allowed');

// These URIs are called by external servers (Flutter POS app, Grab's servers)
// and do not carry CSRF tokens. They use their own auth (Bearer tokens).
return [
    'pos/api/v1/*',
    'pos/webhook/*',
];
