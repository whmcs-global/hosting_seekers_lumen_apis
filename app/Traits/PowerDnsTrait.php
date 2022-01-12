<?php
 
namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait PowerDnsTrait {

	public function wgsReturnDomainId($domainName){
        $resultQuery = DB::connection('mysql3')->table('domains')
        ->select('id')
        ->where('name', $domainName)
        ->first();
		if(!$resultQuery){
			return false;	
		}
		return $resultQuery->id;		
	}
    public function WgsCreateDomain($dataDomain) {
		$domainId = $this->wgsReturnDomainId($dataDomain['domain']);
		if(!$domainId) {
            try{
            $resultQuery = DB::connection('mysql3')->table('domains')->insertGetId([
                'name' => $dataDomain['domain'],
                'master' => NULL,
                'last_check' => NULL,
                'type' => 'MASTER',
                'notified_serial' => NULL,
                'account' => NULL,
            ]);
            } catch(Exception $ex){
                return $ex->get_message();
            }
			$lastid = $resultQuery;
			if($lastid < 1) return "Something went REALLY wrong.Entry not inserted in db";
			// A record Entry
			if(!empty($dataDomain['ipaddress'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => 'www.'.$dataDomain['domain'],
                        'type' => 'A',
                        'content' => $dataDomain['ipaddress'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
            return "success";
		}else{
			return "Domain already exists in database.";			
		}
    }
    public function wgsDeleteDomain($dataDomain){
        $domainId = $this->wgsReturnDomainId($dataDomain);
		if(!$domainId){
			return "Domain not found in database.";
		}else{
            try{
                $resultQuery = DB::connection('mysql3')->table('records')
                ->where('domain_id', $domainId)
                ->delete();
            } catch(Exception $ex){
                return $ex->get_message();
            }
            try{
                $resultQuery = DB::connection('mysql3')->table('domains')
                ->where('id', $domainId)
                ->delete();
            } catch(Exception $ex){
                return $ex->get_message();
            }
			return "success";
		}
    }
    public function wgsReturnDomainData($domainName, $ipaddress){
        $domainId = $this->wgsReturnDomainId($domainName); 
		$response = [];
		if(!$domainId){
			$domainHostName = $this->WgsCreateDomain(['domain' => $domainName, 'ipaddress' => $ipaddress]);
            if($domainHostName == 'success')
            return $this->wgsReturnDomainData($domainName, $ipaddress);
            else{
                $response["status"] = "errorDomain";
                $response["data"] = "Domain not found in power dns database. please contact administrator for more info.";
            }
		}else{
            $resultQuery = DB::connection('mysql3')->table('records')
            ->select('id', 'name', 'type', 'content', 'ttl', 'prio', 'change_date')
            ->where('domain_id', $domainId)
            ->orderBy('name')
            ->orderBy('content')
            ->get()->toArray();
			if(count($resultQuery) < 1){ 
				$response["status"] = "error";
				$response["data"] = "No record found in database.";
			}else{
                $recordArray = [];
                foreach($resultQuery as $row){
                    array_push($recordArray, ['id' => jsencode_userdata($row->id), 'name' => $row->name, 'type' => $row->type, 'content' => $row->content, 'ttl' => $row->ttl, 'prio' => $row->prio]);
                }
				$response["status"] = "success";
				$response["data"] = $recordArray;
			}
		}
		return $response;
    }
    public function wgsUpdateRecordData($recordData,$for){
		if($for == 'update'){
			$stringErrpr = '';
			$response = [];
			foreach($recordData['recordid'] as $key=>$value){
				$recId = trim($recordData['recordid'][$key]);
				$name = trim($recordData['name'][$key]);
				$ttl = trim($recordData['ttl'][$key]);
				if($ttl < 3600) $ttl = 86400;
				$content = trim($recordData['content'][$key]);
				if($recordData['prio'][$value]){
					$prio = trim($recordData['prio'][$value]);
					$priorty = "prio='".strval($prio)."',";
				}
				$query = "UPDATE records SET name='".$dbConnection->escape_string($name)."',content='".$dbConnection->escape_string($content)."',ttl=".strval($ttl).",".$priorty." change_date='".time()."'
				WHERE id=".strval($recId)."";
				$result = $dbConnection->query($query);
				if(!$result){
					$stringErrpr .= "Update failed! ==> ".$name." (".$recId.")<br>";
				}
				if(!empty($stringErrpr)){
					$response["status"] = "error";
					$response["msg"] = $stringErrpr;
				}else{
					$response["status"] = "success";
					$response["msg"] = "Record update successfully";
				}
			}
			return $response;
		}elseif($for == 'delete'){
			$stringErrpr = '';
			$response = [];
			foreach($recordData['recordid'] as $key=>$value){
				if($recordData['deletrecord'][$value]){
					$recId = trim($value);
					$name = trim($recordData['name'][$key]);
					$query = "DELETE FROM records WHERE id=".strval($recId)."";
					$result = $dbConnection->query($query);
					if(!$result){
						$stringErrpr .= "Delete failed! ==> ".$name." (".$recId.")<br>";
					}
					if(!empty($stringErrpr)){
						$response["status"] = "error";
						$response["msg"] = $stringErrpr;
					}else{
						$response["status"] = "success";
						$response["msg"] = "Record Delete successfully";
					}
				}
			}
			return $response;
		}elseif($for == 'insert'){
			$stringErrpr = '';
			$response = [];
			$domainId = $recordData['domainDnsId'];
			$domainName = $recordData['domainDnsName'];
			$domainHostName = $recordData['hostname'];
			$fullHostName = $domainHostName.''.$domainName; 
			$ttl = $recordData['ttl'];
			if($ttl < 3600) $ttl = 86400;
			$type = $recordData['typedns'];
			if($type == 'TXT'){
				$address = $recordData['content'];
			}else{
				$address = $recordData['address'];
			}
			$prior = $recordData['priority'];
			$query = "INSERT INTO records (domain_id,name,type,content,ttl,prio,change_date)
					VALUES('".strval($domainId)."','".$dbConnection->escape_string($fullHostName)."','".$dbConnection->escape_string($type)."','".$dbConnection->escape_string($address)."','".$dbConnection->escape_string($ttl)."','".$dbConnection->escape_string($prior)."',".time().")";
			$result = $dbConnection->query($query);
			if(!$result){
				$stringErrpr .= $dbConnection->error;
			}
			if(!empty($stringErrpr)){
				$response["status"] = "error";
				$response["msg"] = $stringErrpr;
			}else{
				$response["status"] = "success";
				$response["msg"] = "Record Inserted successfully";
			}			
			return $response;
		}
    }
}