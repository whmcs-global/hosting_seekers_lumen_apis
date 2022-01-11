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
    public function WgsCreateDomainPowerDns($dataDomain) {
		$domainId = $this->wgsReturnDomainId($dataDomain['domain']);
		if(!$domainId) {
            try{
            $resultQuery = DB::connection('mysql3')->table('domains')->insertGetId([
                'name' => $dataDomain['domain'],
                'master' => NULL,
                'last_check' => NULL,
                'type' => 'NATIVE',
                'notified_serial' => NULL,
                'account' => NULL,
            ]);
            } catch(Exception $ex){
                return $ex->get_message();
            }
			$lastid = $resultQuery;
			if($lastid < 1) return "Something went REALLY wrong.Entry not inserted in db";
			// NS1 Entry
			if(!empty($dataDomain['ns1'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'NS',
                        'content' => $dataLoop['ns1'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// NS2 Entry
			if(!empty($dataDomain['ns2'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'NS',
                        'content' => $dataLoop['ns2'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// NS3 Entry
			if(!empty($dataDomain['ns3'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'NS',
                        'content' => $dataLoop['ns3'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// NS4 Entry
			if(!empty($dataDomain['ns4'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'NS',
                        'content' => $dataLoop['ns4'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// A record Entry
			if(!empty($dataDomain['ipaddress'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'A',
                        'content' => $dataDomain['ipaddress'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// SOA record Entry
			if(!empty($dataDomain['soa'])){
                try{
                    $resultQuery = DB::connection('mysql3')->table('records')->insert([
                        'domain_id' => strval($lastid),
                        'name' => $dataDomain['domain'],
                        'type' => 'SOA',
                        'content' => $dataDomain['soa'],
                        'ttl' => 86400,
                        'change_date' => time()
                    ]);
                } catch(Exception $ex){
                    return $ex->get_message();
                }
			}
			// A RECORD Update Domain Transfer
			if(!empty($dataDomain['A_RECORD'])){
				foreach($dataDomain['A_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'A',
                            'content' => $dataLoop['ip'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
			// AAAA RECORD Update Domain Transfer
			if(!empty($dataDomain['AAAA_RECORD'])){
				foreach($dataDomain['AAAA_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'AAAA',
                            'content' => $dataLoop['ipv6'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
			// CNAME RECORD Update Domain Transfer
			if(!empty($dataDomain['CNAME_RECORD'])){
				foreach($dataDomain['CNAME_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'CNAME',
                            'content' => $dataLoop['target'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}			
			// HINFO RECORD Update Domain Transfer
			if(!empty($dataDomain['HINFO_RECORD'])){
				foreach($dataDomain['HINFO_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'HINFO',
                            'content' => $dataLoop['target'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
			// MX RECORD Update Domain Transfer
			if(!empty($dataDomain['MX_RECORD'])){
				foreach($dataDomain['MX_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'MX',
                            'content' => $dataLoop['target'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
			// TXT RECORD Update Domain Transfer
			if(!empty($dataDomain['TXT_RECORD'])){
				foreach($dataDomain['TXT_RECORD'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'TXT',
                            'content' => $dataLoop['txt'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
			// SOA RECORD Update Domain Transfer
			if(!empty($dataDomain['SOA_Record'])){
				foreach($dataDomain['SOA_Record'] as $dataLoop){
                    try{
                        $resultQuery = DB::connection('mysql3')->table('records')->insert([
                            'domain_id' => strval($lastid),
                            'name' => $dataDomain['domain'],
                            'type' => 'SOA',
                            'content' => $dataLoop['mname'],
                            'ttl' => $dataLoop['ttl'],
                            'change_date' => time()
                        ]);
                    } catch(Exception $ex){
                        return $ex->get_message();
                    }
				}
			}
		}else{
			return "Domain already exists in database.";			
		}
    }
    public function wgsDeleteDomainPowerDns($dataDomain){
        $domainId = $this->wgsReturnDomainId($dataDomain['domain']);
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
    public function wgsReturnDomainDataClientArea($domainName){
        $domainId = $this->wgsReturnDomainId($domainName); 
		$response = [];
		if(!$domainId){
			$response["status"] = "errorDomain";
			$response["data"] = "Domain not found in power dns database. please contact administrator for more info.";
		}else{
            $resultQuery = DB::connection('mysql3')->table('records')
            ->select('id', 'name', 'type', 'content', 'ttl', 'prio', 'change_date')
            ->where('domain_id', $domainId)
            ->orderBy('name')
            ->orderBy('content')
            ->get();
			if($resultQuery->count() < 1){ 
				$response["status"] = "error";
				$response["data"] = "No record found in database.";
			}else{
				while($subArr[] = $resultQuery->fetch_object());
				$subArr = array_filter($subArr);
				$response["status"] = "success";
				$response["data"] = $subArr;
			}
		}
		return $response;
    }
    public function wgsUpdateRecordDataClientArea($recordData,$for){
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