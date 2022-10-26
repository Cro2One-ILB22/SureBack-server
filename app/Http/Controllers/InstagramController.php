<?php

namespace App\Http\Controllers;

use App\Services\InstagramService;

class InstagramController extends Controller
{
    public function __construct()
    {
        $this->instagramService = new InstagramService();
    }
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Http\Response
     */
    public function __invoke()
    {
        //
    }

    public function profile()
    {
        $username = request()->username;
        return $this->instagramService->getProfile($username);
    }
}
