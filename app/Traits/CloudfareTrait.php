<?php
 
namespace App\Traits;

use Illuminate\Support\Facades\DB;
use App\Models\{ZoneDomain, ZoneRecord, CloudfareUser};
trait CloudfareTrait {

    private $ApiUrl;
    public $ApiEmail;
    public $ApiKey;
    public $userKey;

    public function __construct()
    {
        $getSetting = CloudfareUser::where('status', 1)->first();
        $this->ApiUrl = 'https://api.cloudflare.com/client/v4/';
        $this->userKey = $getSetting->user_key;
        $this->ApiKey = $getSetting->user_api;
        $this->ApiEmail = $getSetting->email;
    }

    public function sendCloudRequest($url, $action, $extra = NULL, $post = NULL, $moduleName = null)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        $cfusername = $this->ApiEmail;
        $cfapikey = $this->ApiKey;
        if (count($extra) > 0) {
            $cfusername = $extra['cfusername'];
            $cfapikey = $extra['cfapikey'];
        }
        $headers = array(
            "Content-Type: application/json",
            "X-Auth-Email: " . $cfusername,
            "X-Auth-Key: " . $cfapikey
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if (strtolower($action) == "get") {
            curl_setopt($ch, CURLOPT_HTTPGET, 1);
        }
        if (strtolower($action) == "post") {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if (strtolower($action) == "put") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if (strtolower($action) == "patch") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        if (strtolower($action) == "delete") {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $json = curl_exec($ch);
        $info = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $result = json_decode($json, true);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 && $error == '' && $result['result'] != '') {
            return $result;
        } else {
            $cferrorcode = $apierror = '';
            if ($result['success'] == '') {
                $apierror = $result['errors'][0]['message'];
                $cferrorcode = $result['errors'][0]['code'];
            }
            $errorArray = array("info" => $info, "error" => $error, "cferrorcode" => $cferrorcode, "apierror" => $apierror);
            return array("result" => "error", "data" => $errorArray);
        }
    }
    
    public function createZoneSet($zone) {
        $ch = curl_init();
        $cfusername = $this->ApiEmail;
        $cfapikey = $this->ApiKey;
        $headers = array(
            "Content-Type: application/json",
            "X-Auth-Email: " . $cfusername,
            "X-Auth-Key: " . $cfapikey
        );
        curl_setopt($ch, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/accounts');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $http_result = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code != 200) {
            return ['error' => $error];
        } 
        $accountDetail = json_decode($http_result, true);
        curl_close($ch);
        if(!is_array($accountDetail) || !$accountDetail['success']){
            return $accountDetail['errors'][0];
        }
        $accoutnId = $accountDetail['result'][0]['id'];
        $resolve_to = 'cloudflare-resolve-to.'.$zone;
        $subdomains = 'www';
        $data = array(
            'type' => 'full',
            'account' => ['id' => $accoutnId],
            'name' => $zone,
        );
        $ch1 = curl_init();
        $headers = array(
            "Content-Type: application/json",
            "X-Auth-Email: " . $cfusername,
            "X-Auth-Key: " . $cfapikey
        );
        curl_setopt($ch1, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/zones');
        curl_setopt($ch1, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch1, CURLOPT_POST, 1);
        curl_setopt($ch1, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch1, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
        $http_result = curl_exec($ch1);
        $error = curl_error($ch1);
        $http_code = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        curl_close($ch1);
        // if ($http_code != 200) {
        //     return ['error' => $error];
        // } 
        return json_decode($http_result);
    }

    public function getSingleZone($zonename, $ApiEmail = null, $ApiKey = null)
    {
        $ApiEmail = $ApiEmail??$this->ApiEmail; 
        $ApiKey = $ApiKey??$this->ApiKey;
        $url = $this->ApiUrl . "zones?name=" . $zonename;
        $action = "get";
        $extra = array("cfusername" => $this->ApiEmail, "cfapikey" => $this->ApiKey, "per_page" => "50");
        $result = $this->sendCloudRequest($url, $action, $extra);        
        return $result;
    }

    public function deleteZone($zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier;
        $action = "delete";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $result = $this->sendCloudRequest($url, $action, $extra);
        return $result;
    }

    public function listDNSRecords($zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/dns_records?per_page=100";
        $action = "get";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $result = $this->sendCloudRequest($url, $action, $extra);
        return $result;
    }

    public function createDNSRecord($dnsdata, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/dns_records";
        $action = "post";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $type = $dnsdata["cfdnstype"];
        $dnsdata["cfdnsttl"] = intval($dnsdata["cfdnsttl"]);
        if ($dnsdata["cfdnsttl"] == '')
            $dnsdata["cfdnsttl"] = 1;
        if ($type == "A" || $type == "AAAA" || $type == "CNAME" || $type == "SPF" || $type == "TXT" || $type == "NS") {
            $post = array("type" => $dnsdata["cfdnstype"], "name" => $dnsdata["cfdnsname"], "content" => $dnsdata["cfdnsvalue"], "ttl" => $dnsdata["cfdnsttl"]);
        }
        if ($type == "MX") {
            $dnsdata["cfmxpriority"] = intval($dnsdata["cfmxpriority"]);
            $post = array("type" => $dnsdata["cfdnstype"], "name" => $dnsdata["cfdnsname"], "content" => $dnsdata["cfdnsvalue"], "priority" => $dnsdata["cfmxpriority"], "ttl" => $dnsdata["cfdnsttl"]);
        }
        if (($type == "A" || $type == "AAAA" || $type == "CNAME") && array_key_exists('proxied', $dnsdata)) {
            switch ($dnsdata["proxied"]) {
                case "false":
                    $post = array_merge($post, array('proxied' => false));
                    break;
                case "true":
                    $post = array_merge($post, array('proxied' => true));
                    break;
            }
        }
        $result = $this->sendCloudRequest($url, $action, $extra, json_encode($post));
        return $result;
    } 
    public function editDNSRecord($dnsdata, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/dns_records/" . $dnsdata["dnsrecordid"];
        $action = "put";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $post = array(
            "id" => $dnsdata["dnsrecordid"],
            "type" => $dnsdata["cfdnstype"],
            "name" => $dnsdata["cfdnsname"],
            "content" => $dnsdata["cfdnsvalue"],
            "ttl" => intval($dnsdata["cfdnsttl"])
        );
        if ($dnsdata["cfdnstype"] == "MX") {
            $dnsdata["cfmxpriority"] = intval($dnsdata["cfmxpriority"]);
            $post['priority'] = $dnsdata["cfmxpriority"];
        }
        if ($dnsdata["cfdnstype"] == "A" || $dnsdata["cfdnstype"] == "AAAA" || $dnsdata["cfdnstype"] == "CNAME") {
            switch ($dnsdata["proxied"]) {
                case "false":
                    $post = array_merge($post, array('proxied' => false));
                    break;
                case "true":
                    $post = array_merge($post, array('proxied' => true));
                    break;
            }
        }
        $result = $this->sendCloudRequest($url, $action, $extra, json_encode($post));
        return $result;
    }
    public function deleteDNSRecord($dnsdata, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/dns_records/" . $dnsdata["dnsrecordid"];
        $action = "delete";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $result = $this->sendCloudRequest($url, $action, $extra);
        return $result;
    }

    public function changeDevelopmentModeSetting($value, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/settings/development_mode";
        $action = "patch";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $post = array("value" => $value);
        $result = $this->sendCloudRequest($url, $action, $extra, json_encode($post));
        return $result;
    }
    
    public function changeSecurityLevelSetting($value, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/settings/security_level";
        $action = "patch";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $post = array("value" => $value);
        $result = $this->sendCloudRequest($url, $action, $extra, json_encode($post));
        return $result;
    }

    public function getModeSetting($value, $zoneidentifier, $ApiEmail, $ApiKey)
    {
        $url = $this->ApiUrl . "zones/" . $zoneidentifier . "/settings/".$value;
        $action = "get";
        $extra = array("cfusername" => $ApiEmail, "cfapikey" => $ApiKey);
        $result = $this->sendCloudRequest($url, $action, $extra);
        return $result;
    }
}