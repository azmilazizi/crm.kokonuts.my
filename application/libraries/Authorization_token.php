<?php defined('BASEPATH') or exit('No direct script access allowed');

require_once APPPATH . 'third_party/php-jwt/JWT.php';
require_once APPPATH . 'third_party/php-jwt/BeforeValidException.php';
require_once APPPATH . 'third_party/php-jwt/ExpiredException.php';
require_once APPPATH . 'third_party/php-jwt/SignatureInvalidException.php';

use Timesheets\Firebase\JWT\JWT as TimeSheets_JWT;

class Authorization_Token
{
    /**
     * Token Key
     */
    protected $token_key;

    /**
     * Token algorithm
     */
    protected $token_algorithm;

    /**
     * Token Request Header Name
     */
    protected $token_header;

    /**
     * Token Expire Time
     * ------------------
     * (1 day) : 60 * 60 * 24 = 86400
     * (1 hour) : 60 * 60 = 3600
     */
    protected $token_expire_time = 432000;

    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();

        /**
         * jwt config file load
         */
        $this->CI->load->config('jwt');

        /**
         * Load Config Items Values
         */
        $this->token_key        = $this->CI->config->item('jwt_key');
        $this->token_algorithm  = $this->CI->config->item('jwt_algorithm');
        $this->token_header     = $this->CI->config->item('token_header');
        $this->token_expire_time = $this->CI->config->item('token_expire_time');
    }

    /**
     * Generate Token
     * @param: {array} data
     */
    public function generateToken($data = null)
    {
        if ($data && is_array($data))
        {
            // add api time key in user array()
            $data['API_TIME'] = time();

            try {
                return TimeSheets_JWT::encode($data, $this->token_key, $this->token_algorithm);
            }
            catch (Exception $e) {
                return 'Message: ' . $e->getMessage();
            }
        }

        return 'Token Data Undefined!';
    }

    public function get_token()
    {
        /**
         * Request All Headers
         */
        $headers = $this->CI->input->request_headers();

        /**
         * Authorization Header Exists
         */
        return $this->token($headers);
    }

    /**
     * Validate Token with Header
     * @return : user informations
     */
    public function validateToken()
    {
        /**
         * Request All Headers
         */
        $headers = $this->CI->input->request_headers();

        /**
         * Authorization Header Exists
         */
        $token_data = $this->tokenIsExist($headers);
        if ($token_data['status'] === TRUE)
        {
            try
            {
                /**
                 * Token Decode
                 */
                try {
                    $token_decode = TimeSheets_JWT::decode($token_data['token'], $this->token_key, [$this->token_algorithm]);
                }
                catch (Exception $e) {
                    return ['status' => FALSE, 'message' => $e->getMessage()];
                }

                if (!empty($token_decode) && is_object($token_decode))
                {
                    // Check Token API Time [API_TIME]
                    if (empty($token_decode->API_TIME) || !is_numeric($token_decode->API_TIME)) {
                        return ['status' => FALSE, 'message' => 'Token Time Not Define!'];
                    }

                    // Check token expiration if configured
                    if ($this->token_expire_time !== NULL && (int) $this->token_expire_time > 0) {
                        $token_age = time() - (int) $token_decode->API_TIME;

                        if ($token_age >= (int) $this->token_expire_time) {
                            return ['status' => FALSE, 'message' => 'Expired token'];
                        }
                    }

                    /**
                     * All Validation False Return Data
                     */
                    return ['status' => TRUE, 'data' => $token_decode];
                }

                return ['status' => FALSE, 'message' => 'Forbidden'];
            }
            catch (Exception $e) {
                return ['status' => FALSE, 'message' => $e->getMessage()];
            }
        }

        // Authorization Header Not Found!
        return ['status' => FALSE, 'message' => $token_data['message']];
    }

    /**
     * Token Header Check
     * @param: request headers
     */
    private function tokenIsExist($headers)
    {
        if (!empty($headers) && is_array($headers)) {
            foreach ($headers as $header_name => $header_value) {
                $token = $this->extractTokenFromHeader($header_name, $header_value);

                if ($token !== null) {
                    return ['status' => TRUE, 'token' => $token];
                }
            }
        }

        return ['status' => FALSE, 'message' => 'Token is not defined.'];
    }

    private function token($headers)
    {
        if (!empty($headers) && is_array($headers)) {
            foreach ($headers as $header_name => $header_value) {
                $token = $this->extractTokenFromHeader($header_name, $header_value);

                if ($token !== null) {
                    return $token;
                }
            }
        }

        return 'Token is not defined.';
    }

    private function extractTokenFromHeader($header_name, $header_value)
    {
        $normalized_header = strtolower(trim($header_name));

        if ($normalized_header === strtolower(trim($this->token_header))) {
            return trim($header_value);
        }

        if ($normalized_header === 'authorization') {
            $value = trim($header_value);

            if (stripos($value, 'bearer ') === 0) {
                return trim(substr($value, 7));
            }

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Override the configured expiration time.
     *
     * Passing 0 (or any value lower than zero) disables the expiration check.
     *
     * @param int|null $expire_time
     *
     * @return void
     */
    public function set_token_expire_time($expire_time)
    {
        if ($expire_time === NULL) {
            $this->token_expire_time = NULL;

            return;
        }

        $this->token_expire_time = max(0, (int) $expire_time);
    }
}
