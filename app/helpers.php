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
            return base64_encode(openssl_encrypt($data, $encryptionMethod, $secret, 0, $iv));
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
            return openssl_decrypt(base64_decode($data), $encryptionMethod, $secret, 0, $iv);
        } catch (\Exception $e) {
            abort('403');
        }
    }
} 