<?PHP

/*
demo 实例


require('curl.class.php');

$max_redirect = 3;  // Skipable: default => 3
$client = new curl_class(array(

	CURLOPT_FRESH_CONNECT => 1,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; ja; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3'

), $max_redirect);

$client->setResponseSequenceFlag(true); // Skipable: default => false
$client->setMetaRedirectFlag(false); // Skipable: default => true
$client->setCompression('gzip,deflate'); // Skipable: default => ''

$url = 'http://example.com/index.php';
$client->get($url);

$url = 'http://example.com/login.php';
$params = array('id' => 'user_id', 'pass' => 'user_pass');
$client->post($url, $params);

$url = 'http://example.com/login_2.php';
$client->get($url);

print_r($client->currentResponse());
echo $client->currentResponse('body');

$cookies = $client->getCurrentCookies();
print_r($cookies);

echo $client->getCurrentCookies('session_key');
*/

class curl_class {

    private $_parse_url;
    private $_options;
    private $_current_redirect;
    private $_max_redirect;
    private $_responses;
    private $_response_parts;
    private $_response_sequence_flag = false;
    private $_meta_redirect_flag = true;
    private $_compression = '';
    private $_cookies = array();
    private $_default_options = array();
    private $_basic_info = array();

    public function __construct($default_options=array(), $max_redirect=3) {

        if($default_options != array()) {

            $this->setDefaultOptions($default_options);

        }

        $this->setMaxRedirect($max_redirect);

    }

    public function setDefaultOptions($default_options) {

        $this->_default_options = $default_options;

    }

    public function setMaxRedirect($max_redirect) {

        $this->_max_redirect = intval($max_redirect);

    }

    public function setResponseSequenceFlag($bool) {

        $this->_response_sequence_flag = $bool;

    }

    public function setMetaRedirectFlag($bool) {

        $this->_meta_redirect_flag = $bool;

    }

    public function setCompression($str) {

        $this->_compression = $str;

    }

    public function get($url) {

		//$this->reset();
        $this->refreshOption();

		//add rhf 2013/5/24 如果是get请求重试三次
		for($i = 0; $i < 3; $i++) {
			$this->request($url);
			if($this->_response_parts[0]['code'] == 200) {
				return ;
			}
		}
    }

    public function post($url, $params) {

        $this->refreshOption();
        $this->_options[CURLOPT_POST] = true;
        $this->_options[CURLOPT_POSTFIELDS] = $this->getPostData($params);
        $this->request($url);

    }

    private function getPostData($datas) {

        $returns = array();

        foreach($datas as $key => $value) {

            $returns[] = $key .'='. urlencode($value);

        }

        return implode('&', $returns);

    }

    private function redirect($target_url, $referer_url) {

        $this->refreshOption();
        $this->_options[CURLOPT_REFERER] = $referer_url;
        $this->request($target_url, true);

    }

    private function refreshOption() {

        $this->_options = $this->_default_options;
        $this->_options[CURLOPT_HEADER] = true;
        $this->_options[CURLOPT_RETURNTRANSFER] = true;
        $this->_options[CURLOPT_SSL_VERIFYPEER] = false;
        $this->_options[CURLOPT_SSL_VERIFYHOST] = false;
        $this->_options[CURLOPT_ENCODING] = $this->_compression;

    }

    private function request($request_url, $redirect_flag=false) {

        if($request_url == '') {

            return false;

        } else {

            $this->setUrlInfo($request_url);
            $url_info = $this->getUrlInfo();
            $host = $url_info['host'];
            $basic_user = $url_info['user'];
            $basic_pass = $url_info['pass'];

            if($basic_user != '' && $basic_pass != '') {

                $basic_code = $basic_user .':'. $basic_pass;
                $this->setBasicCode($host, $basic_code);
                $request_url = str_replace('://'. $basic_code .'@', '://', $request_url);

            } else {

                $basic_code = $this->getBasicCode($host);

            }

            if($basic_code) {

                $this->_options[CURLOPT_USERPWD] = $basic_code;

            }

            $this->_options[CURLOPT_URL] = $request_url;

        }

        if(!$redirect_flag) {

            $this->_current_redirect = 1;
            $this->_response_parts = array();

        }

        $ch = curl_init();
        $this->setOptionsSavedCookie();
        curl_setopt_array($ch, $this->_options);
        curl_exec($ch);
        $curl_info = curl_getinfo($ch);
        $curl_content = curl_multi_getcontent($ch);
        curl_close($ch);

        $url = $curl_info['url'];
        $code = $curl_info['http_code'];
        $headers = $this->getHeaders(substr($curl_content, 0, $curl_info['header_size']));
        $body = substr($curl_content, $curl_info['header_size']);
        $this->_response_parts[] = array(

            'url' => $url,
            'code' => $code,
            'headers' => $headers,
            'body' => $body

        );

        $header_location = $headers['location'];

        if($header_location == '' && $this->_meta_redirect_flag) {

            $header_location = $this->getMetaRedirectUrl($body);

        }

        if($header_location != '' && $this->_current_redirect <= $this->_max_redirect) {

            $this->_current_redirect++;
            $header_location = $this->getAbsolutePath($url, $header_location);
            $this->redirect($header_location, $url);

        } else {

            if($this->_response_sequence_flag) {

                $this->_responses[] = $this->_response_parts;

            } else {

                $this->_responses[0][0] = $this->_response_parts[count($this->_response_parts)-1];

            }

        }

    }

    public function getAbsolutePath($base_url='', $relative_path='') {

        $base_urls = explode('/', $base_url);
        $base_urls_max_number = count($base_urls)-1;
        $base_urls[$base_urls_max_number] = '';
        $base_url = implode('/', $base_urls);

        $parse = array();
        $parse = parse_url($base_url);

        if(preg_match('|^http[s]?://|', $relative_path)) {

            return $relative_path;

        } else if(preg_match('|^/.*$|', $relative_path)) {

            return $parse['scheme'] .'://'. $parse['host']. $relative_path;

        } else {

            $a = $this->getRealArray(split('/', $parse['path']));
            $b = $this->getRealArray(split('/', $relative_path));

            foreach($b as $v){

                if ($v == '.') {}
                else if($v == '..') { array_pop($a); }
                else if($v != '') { array_push($a, $v); }

            }

            $path = join('/', $a);
            return $parse['scheme'] .'://'. $parse['host'] .'/'. $path;

        }

    }

    private function getRealArray($data) {

        $return = array();

        foreach($data as $key => $value) {

            if($value != '') {

                $return[] = $value;

            }

        }

        return $return;

    }

    private function getMetaRedirectUrl($body) {

        if(!preg_match('!<meta\\s+([^>]*http-equiv\\s*=\\s*("Refresh"|\'Refresh\'|Refresh)[^>]*)>!is', $body, $matches)) {

            return '';

        }

        if(!preg_match('!content\\s*=\\s*("[^"]+"|\'[^\']+\'|\\S+)!is', $matches[1], $urlMatches)) {

            return '';

        }

        $parts = explode(';', ('\'' == substr($urlMatches[1], 0, 1) || '"' == substr($urlMatches[1], 0, 1))?
                               substr($urlMatches[1], 1, -1): $urlMatches[1]);

        if(!preg_match('/url\\s*=\\s*("[^"]+"|\'[^\']+\'|\\S+)/is', $parts[1], $urlMatches)) {

            return '';

        }

        return trim($urlMatches[1]);

    }

    private function setUrlInfo($url) {

        $this->_parse_url = parse_url($url);

    }

    private function getUrlInfo($place='') {

        if($place != '') {

            return $this->_parse_url[$place];

        } else {

            return $this->_parse_url;

        }

    }

    private function setBasicCode($host, $code) {

        $this->_basic_info['H:'. $host] = $code;

    }

    private function getBasicCode($host) {

        return $this->_basic_info['H:'. $host];

    }

    private function getHeaders($header_content) {

        if($header_content == '') return '';

        $headers = array();

        preg_match_all('|(.+): (.+)|', $header_content, $matches);
        $matches_count = count($matches[0]);

        if($matches_count > 0) {

            for($loop2 = 0; $loop2 < $matches_count; $loop2++) {

                $header_name = strtolower($matches[1][$loop2]);
                $header_value = trim($matches[2][$loop2]);

                if($header_name == 'set-cookie') {

                    $this->addCookie($header_value);

                } else {

                    $headers[$header_name] = $header_value;

                }

            }

            $header_number++;

        }

        return $headers;

    }

    function addCookie($cookie_content) {

        $cookie_datas = explode(';', $cookie_content);
        $cookie_datas = array_map('trim', $cookie_datas);
        $cookie_datas_count = count($cookie_datas);

        $cookie_contents = array();
        $pattern_1 = '!^(expires|path|domain|secure)=(.*)!i';
        $pattern_2 = '!^([^=]+)=(.*)!';

        for($loop = 0; $loop < $cookie_datas_count; $loop++) {

            $cookie_data = $cookie_datas[$loop];

            if(preg_match($pattern_1, $cookie_data, $matches)) {

                $name = $matches[1];
                $value = $matches[2];
                $cookie_contents[$name] = $value;

            } else if(preg_match($pattern_2, $cookie_data, $matches)) {

                $name = $matches[1];
                $value = $matches[2];
                $cookie_contents['argument'] = array('name' => $name, 'value' => $value);

            }

        }

        $cookie_host = $this->getUrlInfo('host');

        $expires = $cookie_contents['expires'];
        $domain = $cookie_contents['domain'];
        $path = $cookie_contents['path'];
        $secure = intval($cookie_contents['secure']);
        $argument_name = $cookie_contents['argument']['name'];
        $argument_value = $cookie_contents['argument']['value'];

        if($domain == '') $domain = $this->getUrlInfo('host');
        if(substr($domain, 0, 1) == '.') $domain = substr($domain, 1);
        if($path == '') $path = '/';

        $domain_var_name = '[D]:'. $domain;
        $path_var_name = '[P]:'. $path;
        $this->_cookies[$domain_var_name][$path_var_name][$argument_name] = array(

            'value' => $argument_value,
            'expires' => $expires,
            'secure' => $secure

        );

    }

    private function setOptionsSavedCookie() {

        $current_cookies = $this->getCurrentCookies();

        if(count($current_cookies) > 0) {

            $cookie_option_values = array();

            foreach($current_cookies as $cookie_name => $value) {

                $cookie_option_values[] = $cookie_name .'='. $value;

            }

            $this->_options[CURLOPT_COOKIE] = implode('; ', $cookie_option_values);

        } else {

            unset($this->_options[CURLOPT_COOKIE]);

        }

    }

    public function getCurrentCookies($key='') {

        $returns = array();
        $access_protocol = $this->getUrlInfo('protocol');
        $access_domain = $this->getUrlInfo('host');
        $access_path = $this->getUrlInfo('path');
        $cookie_option_values = array();

        foreach($this->_cookies as $cookie_domain => $domain_infos) {

            $cookie_domain = substr($cookie_domain, 4);

            if($cookie_domain == substr($access_domain, 0-strlen($cookie_domain))) {

                foreach($domain_infos as $cookie_path => $cookie_infos) {

                    $cookie_path = substr($cookie_path, 4);
                    $access_domain_path = $access_domain . $access_path;
                    $cookie_domain_path = $cookie_domain . $cookie_path;

                    if(!substr($cookie_domain_path, -1) == '/') {

                        $cookie_domain_path .= '/';

                    }

                    if($cookie_path == '/' || $cookie_domain_path == substr($access_domain_path, 0, strlen($cookie_domain_path))) {

                        foreach($cookie_infos as $cookie_name => $cookie_values) {

                            $secure = $cookie_values['secure'];
                            $value = $cookie_values['value'];

                            if($secure == 0 || ($secure == 1 && $access_protocol == 'https')) {

                                $returns[$cookie_name] = $value;

                            }

                        }

                    }

                }

            }

        }

        if($key != '') {

            return $returns[$key];

        } else {

            return $returns;

        }

    }

    public function response($response_number='') {

        if(!is_numeric($response_number)) {

            return $this->_responses;

        } else {

            return $this->_responses[$response_number];

        }

    }

    public function currentResponse($place='', $number=0) {

        $current_response = $this->response(count($this->_responses) - 1);
        $max_number = count($current_response)-1;

        if($place == '') {

            return $current_response[$max_number];

        } else {

            return $current_response[$max_number][$place];

        }

    }

    public function reset() {

        $this->_cookies = array();
        $this->_responses = array();

    }

}

