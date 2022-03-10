<?php
use Carbon\Carbon;
if (!function_exists('public_path')) {
   /**
    * Get the path to the public folder.
    *
    * @param  string $path
    * @return string
    */
    function public_path($path = '')
    {
        return env('PUBLIC_PATH', base_path('public')) . ($path ? '/' . $path : $path);
    }
}

if (!function_exists('add_days_to_date')) {
    function add_days_to_date(string $date, string $days)
    {
        if($days =='Monthly'){
            $no_of_days = 30;
        }
        elseif($days =='Quarterly'){
            $no_of_days = 91;
        }
        elseif($days =='SemiAnnually'){
            $no_of_days = 182;
        }
        elseif($days =='Annually'){
            $no_of_days = 365;
        }
        $expiry_date = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $date);
        $expiry_date = $expiry_date->addDays($no_of_days);
        $expiry_date = \Carbon\Carbon::parse($expiry_date)->format('Y-m-d');
        return $expiry_date;
    }
}
if (!function_exists('change_date_format')) {
    function change_date_format(string $date, string $format = 'd F Y')
    {
        return \Carbon\Carbon::parse($date)->format($format);
    }
}
if (!function_exists('encode_userdata')) {
    function encode_userdata(string $data)
    {
        try {
            $data = 'HS00'.$data.rand(1000, 9999);
            $encodeData = base64_encode($data);
            return $encodeData;
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('decode_userdata')) {
    function decode_userdata(string $data)
    {
        try {
            $decodeData = base64_decode($data);
            $decodeData = substr($decodeData, 4, -4);
            return $decodeData;
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('jsencode_userdata')) {
    function jsencode_userdata(string $data, string $encryptionMethod = null, string $secret = null)
    {
        $encryptionMethod = config('constants.encryptionMethod');
        $secret = config('constants.secrect');
        try {
            $iv = substr($secret, 0, 16);
            $jsencodeUserdata = str_replace('/', '!', openssl_encrypt($data, $encryptionMethod, $secret, 0, $iv));
            return $jsencodeUserdata;
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('jsdecode_userdata')) {
    function jsdecode_userdata(string $data, string $encryptionMethod = null, string $secret = null)
    {
        $encryptionMethod = config('constants.encryptionMethod');
        $secret = config('constants.secrect');
        try {
            $iv = substr($secret, 0, 16);
            $data = str_replace('!', '/', $data);
            $jsencodeUserdata = openssl_decrypt($data, $encryptionMethod, $secret, 0, $iv);
            return $jsencodeUserdata;
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('jsencode_api')) {
    function jsencode_api(string $data, string $encryptionMethod = null, string $secret = null)
    {
        $encryptionMethod = config('constants.encryptionMethod');
        $secret = config('constants.secrect');
        try {
            $iv = substr($secret, 0, 16);
            return base64_encode(openssl_encrypt($data, $encryptionMethod, $secret, 0, $iv));
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('jsdecode_api')) {
    function jsdecode_api(string $data, string $encryptionMethod = null, string $secret = null)
    {
        $encryptionMethod = config('constants.encryptionMethod');
        $secret = config('constants.secrect');
        try {
            $iv = substr($secret, 0, 16);
            return openssl_decrypt(base64_decode($data), $encryptionMethod, $secret, 0, $iv);
        } catch (\Exception $e) {
            abort('403');
        }
    }
}
if (!function_exists('hitCurl')) {
    function hitCurl($url, $method = 'POST', $data = null, $hearders = array('Content-Type: application/json')){
        $curl = curl_init();
        if($data)
        $data = json_encode($data);
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $hearders,
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}