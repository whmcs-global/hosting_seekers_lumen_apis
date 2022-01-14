<?php
namespace App\Traits;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer, CompanyServer};
use PleskX\Api\Client;

trait PleskTrait {
	/*
    API Method Name:    runPleskQuery
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get User server from Database
    */
	public function runPleskQuery(){
        $serverId = jsdecode_userdata(request()->cpanel_server);
        $server = UserServer::where(['user_id' => request()->userid, 'id' => $serverId])->first();
        if(!$server)
        return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
		if( $server ){
            $linkserver = $server->company_server_package->company_server->link_server ? unserialize($server->company_server_package->company_server->link_server) : 'N/A';
            $controlPanel = null;
            if('N/A' == $linkserver)
            return config('constants.ERROR.FORBIDDEN_ERROR');
			$this->client = new Client($server->company_server_package->company_server->ip_address);
			$this->client->setCredentials($linkserver['username'], $linkserver['apiToken']);
		}else{

		}
        
	}
	
	public function getSiteId(){
        $serverId = jsdecode_userdata(request()->cpanel_server);
        $server = UserServer::where(['user_id' => request()->userid, 'id' => $serverId])->first();
        if(!$server)
        return response()->json(['api_response' => 'error', 'status_code' => 400, 'data' => 'Fetching error', 'message' => config('constants.ERROR.FORBIDDEN_ERROR')]);
			
		$request = <<<STR
		<packet>
			<site>
			<get>
			<filter>
				<name>{$server->domain}</name>
			</filter>
			<dataset>
				<disk_usage/>
				<stat/>
				<gen_info/>
				<hosting/>
				<prefs/>
			</dataset>
			</get>
			</site>
		</packet>
		STR;
		$response = $this->client->request($request);
		return (string)$response->id;
	}
}