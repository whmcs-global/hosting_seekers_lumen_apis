<?php

namespace App\Http\Controllers\v1\delegate\plesk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PleskX\Api\Client;
use Illuminate\Support\Facades\Validator;
class PhpVersionController extends Controller
{
    /*
    Method Name:    getVersion
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get all domains(Webspaces) on Plesk
    Params:         client of plesk
    */
    public function getVersion( Client $client ){
        try{
            $phpVersions = $client->request('<packet>
            <mail>
            <get_info>
               <filter>
                  <site-id>11</site-id>
               </filter>
               <mailbox/>
               <mailbox-usage/>
            </get_info>
            </mail>
            </packet>');
          dd($phpVersions);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => compact('all_domains'), 'message' => 'Domains fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    getDetail
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get detail of the domain
    Params:         client of plesk and request input
    */
    public function getDomain( Client $client , Request $request){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $domain_detail = $client->Webspace()->get("name",$request->domain);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => compact('domain_detail') , 'message' => 'Domain fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    delete
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Delete the domain
    Params:         client of plesk
    */
    public function delete( Client $client , Request $request ){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain' => 'required|string'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $client->Webspace()->delete("name",$request->domain);
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => [] , 'message' => 'Domain deleted successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
    /*
    Method Name:    getPlans
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Get plans
    Params:         client of plesk
    */
    public function getPlans( Client $client ){
        try{
            $all_plans = $client->ServicePlan()->getAll();
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => compact('all_plans'), 'message' => 'Domains fetched successfully.' 
            ]);
        }catch(\Exception $e){
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }

    /*
    Method Name:    create
    Developer:      Shine Dezign
    Created Date:   2021-12-21 (yyyy-mm-dd)
    Purpose:        Create the domain
    Params:         client of plesk
    */
    public function create( Client $client , Request $request ){
        $messages = [
            'domain.required' => 'We need to know domain'
        ];
        $rules = [
            'domain' => 'required|string',
            'plan_name'     =>  'required'
        ];
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'api_response' => 'error', 'status_code' => 422, 'data' => $validator->errors()->all() , 'message' => "Something went wrong."
            ]);
        }
        try{
            $ip_address = $client->ip()->get();
            $ip_address = reset( $ip_address );
            $domain = $client->webspace()->create([
                    'name' => $request->domain,
                    'ip_address' => $ip_address->ipAddress
                ]
            );
            return response()->json([
                'api_response' => 'success', 'status_code' => 200, 'data' => compact('domain') , 'message' => 'Domain created successfully.'
            ]);
        }catch(\Exception $e){
            //$this->delete($client,$request);
            return response()->json([
                'api_response' => 'error', 'status_code' => 400, 'data' => [ ], 'message' => $e->getMessage()
            ]);
        }
    }
}
