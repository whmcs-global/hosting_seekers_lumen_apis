<?php
namespace App\Http\Middleware;

use Closure;
use App\Traits\SendResponseTrait;
use App\Models\{DelegateDomainAccess, DelegatePermission};

class CpanelAccessMiddleware
{
    use SendResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        
        $permission = DelegatePermission::where('name', 'cPanel Access')->pluck('id')->toArray();
        $id = $request->route('id') ?? $request->cpanel_server;
        $delegateDomainAccess = DelegateDomainAccess::where(['delegate_account_id' => $request->delegate_account_id, 'user_server_id' => jsdecode_userdata($id)])->whereHas('delegate_domain_access', function( $qu ) use($permission){
            $qu->where(['delegate_permission_id' => $permission[0], 'status' => 1]);
        })->first();
        if(!$delegateDomainAccess)
        return $this->apiResponse('error', '401', 'Invalid access token');
        return $next($request);
    }
}