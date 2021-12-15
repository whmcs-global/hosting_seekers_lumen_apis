<?php
namespace App\Http\Middleware;

use Closure;
use App\Traits\SendResponseTrait;
use App\Models\{UserToken, DelegateAccount};

class DelegateMiddleware
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
        if ($request->header('delegate-token')) {
            $key = jsdecode_userdata($request->header('delegate-token'));
            $user = DelegateAccount::where(['id' => $key, 'user_id' => $request->userid, 'delegate_user_id' => $request->delegate_user_id])->first();

            if(empty($user)){
                return $this->apiResponse('error', '401', 'Invalid access token');
            }
            return $next($request);
        }
        else{
            return $this->apiResponse('error', '401', 'Invalid access token');
        }
    }
}