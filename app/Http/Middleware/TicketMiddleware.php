<?php
namespace App\Http\Middleware;

use Closure;
use App\Traits\SendResponseTrait;
use App\Models\{UserToken, User, Role};

class TicketMiddleware
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
            if($user){
                $company = User::join('model_has_roles as role', 'role.model_id', '=', 'users.id')->where('id', $user->user_id)->first();
                $role = Role::where('id', $company->role_id)->first();
                // return $this->validateToken($key[1], unserialize($request->header('requestDetail')));
                if($this->validateToken($key[1], unserialize($request->header('requestDetail')))){
                    $request->request->add(['user_id' => $company->id]);
                    $request->request->add(['role' => $role->name]);
                    $request->request->add(['role_id' => $company->role_id]);
                    return $next($request);
                }
            }
        }
        return $this->apiResponse('error', '401', 'Invalid access token 5');
    }
}