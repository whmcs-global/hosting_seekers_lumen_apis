<?php
namespace App\Http\Middleware;

use Closure;
use App\Traits\SendResponseTrait;
use App\Models\{UserToken, User, Role};

class CheckTokenMiddleware
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
        if ($request->header('Authorization')) {
            $key = explode(' ',$request->header('Authorization'));
            $user = UserToken::where('access_token', $key[1])->first();
            if(empty($user)){
                return $this->apiResponse('error', '401', 'Invalid access token');
            }
            $company = User::join('model_has_roles as role', 'role.model_id', '=', 'users.id')->where('id', $user->user_id)->first();
            $role = Role::find($company->role_id);
            if($role->name != 'User')
                return $this->apiResponse('error', '401', 'Invalid access token');
            $to = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $user->updated_at);
            $from = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', date('Y-m-d H:i:s'));


            $diff_in_minutes = $to->diffInMinutes($from);
            $diffToCheck = 30 - $diff_in_minutes;
            if($diffToCheck < 5 && $diffToCheck > 0)
                UserToken::where('access_token', $key[1])->update(['updated_at' => date('Y-m-d H:i:s')]);
            elseif($diff_in_minutes > 30)
                return $this->apiResponse('error', '401', 'Invalid access token');
                
            // if(!$this->validateToken($key[1]))
            //     return $this->apiResponse('error', '401', 'Invalid access token');
            return $next($request);
        }
        else{
            return $this->apiResponse('error', '401', 'Invalid access token');
        }
    }
}