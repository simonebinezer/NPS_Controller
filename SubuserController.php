<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class SubuserController extends BaseController
{
    public function __construct()
    {
        if (session()->get('role') != "user") {
            echo 'Access denied';
            exit;
        }
    }
    public function index()
    {
        return view("subuser/dashboard");
    }
}
