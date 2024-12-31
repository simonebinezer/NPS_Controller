<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Controllers\TenantController;
use App\Models\SubscriptionModel;
use App\Models\UserModel;
use App\Models\TenantModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class CxanalytixController extends BaseController
{
    public function cxindex()
    {
        $isRedirect = 0;
        $userName = null;
        if (session()->get('isLoggedIn')) {
            $userName = session()->get('firstname');
            $subName = "Upgrade to premium";
        } else {
            $subName = "Get 30 Days Free Trial";
        }
        if ($this->request->getGet("redirect")) {
            $isRedirect = 1;
        }
        $result = $this->GetSubscriptionList();
        return view('cx', ["isRedirect" => $isRedirect, "userName" => $userName, "subscriptionList" => $result, "subName" => $subName]);
    }

    public function GetSubscriptionList()
    {
        $model = new SubscriptionModel();
        $result = $model->whereNotIn("subscription_id", [1])->findAll();
        $subscrptionList = [];
        foreach ($result as $key => $value) {
            $value["price"] =4000;
            array_push($subscrptionList, $value);
        }
        return $subscrptionList;
    }

    public function Getsubscription()
    {
        $result = $this->GetSubscriptionList();

        return json_encode(['success' => true, 'csrf' => csrf_hash(), 'output' => $result]);
    }
    public function cxblog()
    {
        return view('cx-blog');
    }

    public function Subscribe()
    {
        if (session()->get('firstname')) {
            $request = $this->request->getGet();
            $model = new SubscriptionModel();
            $condition = ["subscription_id" => $request["subscriptionId"]];
            $subscription = $model->where($condition)->first();
            return view("payPal", ["subscription" => $subscription]);
        } else {
            return  redirect()->to(base_url("signup"));
        }
    }
    public function HowItWorks()
    {
        return view("howItWorks");
    }
}
