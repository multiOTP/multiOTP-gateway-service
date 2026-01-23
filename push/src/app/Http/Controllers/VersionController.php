<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VersionController extends Controller
{
    //
    public function get()
    {
        return response()->json(['data' => ['status' => true, 'version' => config('app.version')]], 200);
    }
}
