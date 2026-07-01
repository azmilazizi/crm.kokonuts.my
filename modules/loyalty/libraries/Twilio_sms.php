<?php defined('BASEPATH') or exit('No direct script access allowed');

class Twilio_sms
{
    private $account_sid;
    private $auth_token;
    private $from_number;
    private $api_base = 'https://api.twilio.com/2010-04-01/Accounts/';

    public function __construct()
    {
        $this->account_sid  = get_option('twilio_account_sid');
        $this->auth_token   = get_option('twilio_auth_token');
        $this->from_number  = get_option('twilio_from_number');
    }

    public function is_configured()
    {
        return !empty($this->account_sid) && !empty($this->auth_token) && !empty($this->from_number);
    }

    /**
     * Send a single SMS. Returns true on success, error string on failure.
     */
    public function send($to, $body)
    {
        $to = $this->normalize_phone($to);
        if (!$to) return 'Invalid phone number';

        $url = $this->api_base . $this->account_sid . '/Messages.json';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $this->account_sid . ':' . $this->auth_token,
            CURLOPT_POSTFIELDS     => http_build_query([
                'To'   => $to,
                'From' => $this->from_number,
                'Body' => $body,
            ]),
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) return 'cURL error';

        $data = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300) return true;

        return isset($data['message']) ? $data['message'] : 'Unknown error (HTTP ' . $http_code . ')';
    }

    /**
     * Send to multiple phone numbers. Returns ['sent' => int, 'failed' => int].
     */
    public function send_bulk($phones, $body)
    {
        $sent = 0;
        $failed = 0;
        foreach ($phones as $phone) {
            $result = $this->send($phone, $body);
            $result === true ? $sent++ : $failed++;
        }
        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Normalize Malaysian phone numbers to E.164 format (+601XXXXXXXX).
     */
    private function normalize_phone($phone)
    {
        // Strip spaces, dashes, brackets
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (empty($phone)) return null;

        if (substr($phone, 0, 1) === '+') return $phone;             // already E.164
        if (substr($phone, 0, 2) === '60') return '+' . $phone;     // 60xxxxxxx
        if (substr($phone, 0, 1) === '0') return '+6' . $phone;     // 01xxxxxxx
        return '+60' . $phone;                                        // bare number
    }
}
