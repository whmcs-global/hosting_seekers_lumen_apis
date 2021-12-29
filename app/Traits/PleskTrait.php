<?php
namespace App\Traits;
use App\Models\{Order, OrderTransaction, CompanyServerPackage, UserServer, CompanyServer};
use PleskX\Api\Client;

trait PleskTrait {
	/*
    API Method Name:    runQuery
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get User server from Database
    */
	public function runQuery(){
		$server = UserServer::find( jsdecode_userdata(request()->cpanel_server) );
		if(is_null($server))
            return config('constants.ERROR.FORBIDDEN_ERROR');
		if( $server ){
			$this->client = new Client($server->domain);
	        $this->client->setCredentials($server->name, $server->password);
		}else{

		}
        
	}
}