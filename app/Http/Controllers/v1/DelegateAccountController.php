<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use App\Models\{User, DelegateAccount, DelegateDomainAccess, DelegateDomainAccessPermission, DelegatePermission, UserServer};
use App\Traits\{AutoResponderTrait, SendResponseTrait};
use DB;

class DelegateAccountController extends Controller
{
    use SendResponseTrait, AutoResponderTrait;
    /*
    API Method Name:    permissionList
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get all permissions
    */
    public function permissionList(Request $request)
    {
        try{
            $records = DelegatePermission::where('status', 1)->get();
            $ratingArray = [];
            if($records->isNotEmpty()){                
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'name' => $row->name, 'slug' => $row->slug]);
                }
                
                $rating['data'] = $permissionData;
                $ratingArray = ['refinedData' => $rating];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method permissionList */

    /*
    API Method Name:    accountList
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of delegate acconts of a user
    */
    public function accountList(Request $request)
    {
        try{
            $records = DelegateAccount::where(['status' => 1, 'user_id' => $request->userid])->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'first_name' => $row->delegate_user->first_name, 'last_name' => $row->delegate_user->last_name]);
                }
                
                $rating['data'] = $permissionData;
                $ratingArray = ['refinedData' => $rating];
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method accountList */

    /*
    API Method Name:    createAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of delegate acconts of a user
    */
    public function createAccount(Request $request)
    {
		$validator = Validator::make($request->all(),[
            'user_id' => 'required|string',
            'access_type' => 'required|string'
        ]);
        if($validator->fails()){
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }
        try{
            DB::beginTransaction();
            $userId = jsdecode_userdata($request->user_id);
            $user = User::where(['id' => $userId])->first();          
            if(!$user)
                return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
            $delegateAccount = DelegateAccount::updateOrCreate(['user_id' => $request->userid, 'delegate_user_id' => $userId]);
            if('full' == $request->access_type){
                $permissions = DelegatePermission::where(['status' => 1])->get(); 
                $servers = UserServer::where(['user_id' => $request->userid])->get();                
                if(!$servers->isNotEmpty())
                {
                    DB::rollBack();
                    return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
                }
                foreach($servers as $server){
                    $delegateDomain = DelegateDomainAccess::updateOrCreate(['delegate_account_id' => $delegateAccount->id, 'user_server_id' => $server->id]);
                    foreach($permissions as $permission){
                        DelegateDomainAccessPermission::updateOrCreate(['delegate_domain_access_id' => $delegateDomain->id, 'delegate_permission_id' => $permission->id]);
                    }
                }
            } elseif('custom' == $request->access_type){
                foreach($request->servers as $server){
                    $delegateDomain = DelegateDomainAccess::updateOrCreate(['delegate_account_id' => $delegateAccount->id, 'user_server_id' => jsdecode_userdata($server->domain)]);
                    foreach($server->permissions as $permission){
                        DelegateDomainAccessPermission::updateOrCreate(['delegate_domain_access_id' => $delegateDomain->id, 'delegate_permission_id' => jsdecode_userdata($permission)]);
                    }
                }
            } else{
                DB::rollBack();
                return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
            }
            DB::commit();
            return $this->apiResponse('success', '200', 'Delegate account has been created successfully.');
        }  catch(\Exception $e){
            DB::rollBack();
            dd($e);
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method createAccount */
}