<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Exception;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{    
    public function __construct()
    {
        $this->middleware('auth:api');
        
        $this->middleware('checkAdmin');
    }

    public function view()
    {
        return response()->json(['message' => 'Successfully viewed admin board']);
    }

    //Clear config cache
    public function clearCache()
    {
        try {
            $clearCache = Artisan::call('cache:clear');
            $optimize = Artisan::call('optimize');
            $configCache = Artisan::call('config:cache');            
                                    
        } catch(Exception $e) {
            
            return response()->json(
                ['errors' => $e->getMessage()], 
                Response::HTTP_NOT_FOUND
            );
        }        
        return response()->json(['message' => 'Successfully cleared cache']);
    }
    
}
