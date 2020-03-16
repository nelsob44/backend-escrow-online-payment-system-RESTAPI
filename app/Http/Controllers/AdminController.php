<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    
}
