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
    API Method Name:    domainList
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of domains of a user
    */
    public function domainList(Request $request)
    {
        try{
            $ratingArray = ['domains' => '', 'permissions' => ''];
            $permissionRecords = DelegatePermission::where('status', 1)->get();
            if($permissionRecords->isNotEmpty()){                
                $permissionData = [];
                foreach($permissionRecords as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'name' => $row->name, 'displayname' => $row->displayname, 'slug' => $row->slug]);
                }
                $ratingArray['permissions'] = $permissionData;
            }
            $records = UserServer::where(['user_id' => $request->userid])->get();
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'name' => $row->name, 'domain' => $row->domain, 'server_location' => $row->company_server_package->company_server->state->name.', '.$row->company_server_package->company_server->country->name]);
                }
                $ratingArray['domains'] = $permissionData;
            }
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method domainList */

    /*
    API Method Name:    accountList
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of delegate acconts of a user
    */
    public function accountList(Request $request, $id = null)
    {
        try{
            $records = DelegateAccount::when(($id != ''), function($q) use($id){
                $q->where('id', jsdecode_userdata($id));
            })->where(['status' => 1, 'user_id' => $request->userid])->get();
            $ratingArray = [];
            if($records->isNotEmpty()){ 
                $permissionData = [];
                foreach($records as $row){
                    $domainArray = [];
                    foreach($row->delegate_domain_access as $domain){
                        $permissions = [];
                        foreach($domain->delegate_domain_access as $permission){
                            array_push($permissions, ['id'=> jsencode_userdata($permission->delegate_permission->id), 'name' => $permission->delegate_permission->name, 'displayname' => $permission->delegate_permission->displayname, 'slug' => $permission->delegate_permission->slug]);
                        }
                        array_push($domainArray, ['id'=> jsencode_userdata($domain->id), 'name' => $domain->user_server->name, 'domain' => $domain->user_server->domain, 'permissions' => $permissions]);
                    }
                    array_push($permissionData, ['id'=> jsencode_userdata($row->id), 'first_name' => $row->delegate_user->first_name, 'last_name' => $row->delegate_user->last_name, 'domains' => $domainArray, 'created_at' => $row->updated_at]);
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
    API Method Name:    searchUser
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get a user deatils on the basis of email
    */
    public function searchUser(Request $request)
    {
        
        $validateData = [
            'email' => 'required|email:rfc,dns'
        ];
		$validator = Validator::make($request->all(), $validateData);
        if($validator->fails()){
            return $this->apiResponse('error', '422', $validator->errors()->all());
        }
        try{
            $email = $request->email;
            if('gmail.com' == explode("@",$email)['1']){
                $preEmail = str_replace('.', '', explode("@",$email)['0']);
                $email = $preEmail.'@gmail.com';
            }
            $records = User::where('id', '!=', $request->userid)->where(['email' => $email])->first();
            if(!$records)
                return $this->apiResponse('success', '200', 'User not found');
            $ratingArray = [];
            $permissionData = ['id'=> jsencode_userdata($records->id), 'first_name' => $records->first_name, 'last_name' => $records->last_name];
            
            $rating['data'] = $permissionData;
            $ratingArray = ['refinedData' => $rating];
            return $this->apiResponse('success', '200', 'Data fetched', $ratingArray);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method searchUser */

    /*
    API Method Name:    deleteAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete delegate acconts of a user
    */
    public function deleteAccount(Request $request, $id)
    {
        try{
            DelegateAccount::where(['id' => jsdecode_userdata($id), 'user_id' => $request->userid])->delete();
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
            return $this->apiResponse('success', '200', 'Delegate account has been deleted', $ratingArray);
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method deleteAccount */

    /*
    API Method Name:    deleteDomain
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To delete delegate access domain of a user
    */
    public function deleteDomain(Request $request, $id)
    {
        try{
            $domain = DelegateDomainAccess::where(['id' => jsdecode_userdata($id)])->whereHas('delegate_account', function( $qu ) use($request){
                $qu->where('user_id', $request->userid);
            })->first();
            if($domain){
                $records = DelegateDomainAccess::where(['delegate_account_id' => $domain->delegate_account_id])->count();
                if($records > 1){
                    $domain->delete();
                    return $this->apiResponse('success', '200', 'Delegate Domain Access has been deleted');
                }
            }
            return $this->apiResponse('error', '404', 'Sorry! You canot delete single delegate domain access');
        }  catch(\Exception $e){
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method deleteDomain */

    /*
    API Method Name:    createAccount
    Developer:          Shine Dezign
    Created Date:       2021-11-24 (yyyy-mm-dd)
    Purpose:            To get list of delegate acconts of a user
    */
    public function createAccount(Request $request)
    {
        $validateData = [
            'user_id' => 'required|string',
            'access_type' => 'required|string'
        ];
        if('custom' == $request->access_type){
            $validateData['domains'] = 'required|array';
            $validateData['permissions'] = 'required|array';
        }
		$validator = Validator::make($request->all(), $validateData);
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
            $permissions = DelegatePermission::where(['status' => 1])->get(); 
            if('full' == $request->access_type){
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
                foreach($request->domains as $server){
                    $servers = UserServer::where(['id' => jsdecode_userdata($server), 'user_id' => $request->userid])->first(); 
                    if(!$servers)
                    {
                        DB::rollBack();
                        return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
                    } 
                    $delegateDomain = DelegateDomainAccess::updateOrCreate(['delegate_account_id' => $delegateAccount->id, 'user_server_id' => jsdecode_userdata($server)]);
                    $permissionArray = [];
                    foreach($request->permissions as $permission){
                        $servers = DelegatePermission::where(['id' => jsdecode_userdata($permission)])->first(); 
                        if(!$servers)
                        {
                            DB::rollBack();
                            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
                        } 
                        array_push($permissionArray, jsdecode_userdata($permission));
                        DelegateDomainAccessPermission::updateOrCreate(['delegate_domain_access_id' => $delegateDomain->id, 'delegate_permission_id' => jsdecode_userdata($permission)]);
                    }
                    DelegateDomainAccessPermission::where(['delegate_domain_access_id' => $delegateDomain->id])->whereNotIn('delegate_permission_id', $permissionArray)->delete();
                }
            } else{
                DB::rollBack();
                return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
            }
            DB::commit();
            return $this->apiResponse('success', '200', 'Delegate account has been created successfully.');
        }  catch(\Exception $e){
            DB::rollBack();
            return $this->apiResponse('error', '404', config('constants.ERROR.FORBIDDEN_ERROR'));
        }
    }
    /* End Method createAccount */
}