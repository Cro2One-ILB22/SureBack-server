<?php

namespace App\Http\Controllers;

use App\Jobs\TestJob;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function index()
    {
        // command
        // Artisan::call('test:start', [
        //     'something' => 'test'
        // ]);

        // $data = [
        //     'name' => 'test',
        //     'success' => true
        // ];
        // $json = json_encode($data);
        // TestJob::dispatch($json);
        return view('welcome');
    }
}
