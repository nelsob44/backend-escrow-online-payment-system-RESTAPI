<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\SignUpRequest;
use App\User;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'signup']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $credentials = request(['email', 'password']);

        if (! $token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Email/Password combination does not exist'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function signup(SignUpRequest $request)
    {
        \Log::info($request->all());
        User::create($request->all());
        return $this->login($request);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    public function profile(Request $request)
    {
       
        $firstname = $request->firstname;
        $lastname = $request->lastname;
        $email = auth()->user()->email;        
        $phone = $request->phone;
        $country = $request->country;

        if($request->theImage) {
            $profile = User::where('email', $email)
            ->update([
                'firstname' => $firstname,
                'lastname' => $lastname,
                'profile_pic' => $request->theImage['paths'][0],
                'phone' => $phone,
                'country' => $country
            ]);

            $user = User::where('email', $email)->first();
                
        return response(['profile' => $user]);
        }

        $profile = User::where('email', $email)
            ->update([
                'firstname' => $firstname,
                'lastname' => $lastname,                
                'phone' => $phone,
                'country' => $country
            ]);
            $user = User::where('email', $email)->first();
        
        return response(['profile' => $user]);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user_info' => auth()->user()
        ]);
    }
}