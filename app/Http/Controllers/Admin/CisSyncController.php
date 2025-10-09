<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CisSyncController extends Controller
{
    public function run()
    {
        set_time_limit(0); // unlimited execution time
        ini_set('max_execution_time', 0); // for web server context

        \Artisan::call('cis:sync-users');
        return back()->with('status', 'Sync CIS dijalankan: ' . now()->toDateTimeString());
    }
}
