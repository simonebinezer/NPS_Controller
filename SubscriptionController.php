<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\ApiLibrary;
use App\Models\PaymentCapturesModel;
use App\Models\PayPalErrorModel;
use App\Models\PaypalLinksModel;
use App\Models\PayPalUserModel;
use App\Models\PurchaseModel;
use App\Models\SubscriptionModel;
use Exception;

class SubscriptionController extends BaseController
{
    private $apiLibrary;
    private $errorModel;
    public function __construct()
    {
        $this->apiLibrary = new ApiLibrary();
        $this->errorModel=new PayPalErrorModel();
    }

    public function GetToken()
    {

        $endpoint = "/v1/oauth2/token";
        $queryParams = ["grant_type" => "client_credentials"];
        $auth = true;
        $requestFormat = "urlencode";
        try {
            $result = $this->apiLibrary->PostData($endpoint, $queryParams, $requestFormat, null, $auth);
        } catch (Exception $ex) {
            $this->errorModel->InsertError($ex->getMessage(), $ex->getCode(), $ex->getTraceAsString());

        }
        return $result->access_token;
    }

    public function CreateOrder()
    {
        $token = $this->GetToken();
        $endpoint = "/v2/checkout/orders";
        $request = $this->request->getJSON();
        $orderData = [
            "intent" => "CAPTURE",
            "purchase_units" => [[
                "amount" => [
                    "currency_code" => $request->cart[0]->currency_code,
                    "value" => $request->cart[0]->value
                ]
            ]]
        ];

        $auth = false;
        $requestFormat = "json";
        try {
            $result = $this->apiLibrary->PostData($endpoint, $orderData, $requestFormat, $token, $auth);
            if ($result->status_code == 400) {
                throw new Exception("bad request", $result->status_code);
            }
        } catch (Exception $ex) {
            //throw $ex;
            $this->errorModel->InsertError($ex->getMessage(), $ex->getCode(), $ex->getTraceAsString());

        }
        return $this->response->setJSON($result);
    }

    public function CaptureOrder()
    {
        $token = $this->GetToken();
        $request = $this->request->getJSON();
        $endpoint = "/v2/checkout/orders/$request->orderID/capture";
        $queryParams = [];
        $auth = false;
        $requestFormat = "json";
        try {
            $result = $this->apiLibrary->PostData($endpoint, $queryParams, $requestFormat, $token, $auth);
        } catch (Exception $ex) {
            $this->errorModel->InsertError($ex->getMessage(), $ex->getCode(), $ex->getTraceAsString());
        }
        return $this->response->setJSON($result);
    }

    public function SavePaymentData()
    {
        $request = $this->request->getPost();
        $order = $request["order"];
        $model = new PaypalLinksModel();
        $order["purchase_units"][0]["payments"]["captures"][0]["links"];
        $captures = $order["purchase_units"][0]["payments"]["captures"][0]["links"][0]["href"];
        $capturesRefund = $order["purchase_units"][0]["payments"]["captures"][0]["links"][1]["href"];
        $orders = $order["purchase_units"][0]["payments"]["captures"][0]["links"][2]["href"];

        $data = ["captures" => $captures, "captures_refund" => $capturesRefund, "orders" => $orders];
        $linkId = $model->insert($data);


        $model = new PayPalUserModel();
        $data = [];
        $email = $order["payment_source"]["paypal"]["email_address"];
        $accountId = $order["payment_source"]["paypal"]["account_id"];
        $name = $order["payment_source"]["paypal"]["name"]["given_name"];
        $surname = $order["payment_source"]["paypal"]["name"]["surname"];
        $businessName = array_key_exists("business_name", $order["payment_source"]["paypal"]) ? $order["payment_source"]["paypal"]["business_name"] : null;

        $accountStatus = $order["payment_source"]["paypal"]["account_status"];
        $address1 = $order["purchase_units"][0]["shipping"]["address"]["address_line_1"];
        $address2 = $order["purchase_units"][0]["shipping"]["address"]["admin_area_2"];
        $address3 = $order["purchase_units"][0]["shipping"]["address"]["admin_area_1"];
        $address4 = $order["purchase_units"][0]["shipping"]["address"]["postal_code"];
        $countryCode = $order["purchase_units"][0]["shipping"]["address"]["country_code"];
        $address = [$address1, $address2, $address3, $address4];
        $address = implode(";", $address);
        $data = ["account_id" => $accountId, "account_status" => $accountStatus, "name" => $name, "surname" => $surname, "business_name" => $businessName, "email" => $email, "country_code" => $countryCode, "address" => $address];
        $paypalUserId = $model->insert($data);

        $model = new PaymentCapturesModel();
        $paymentId = $order["purchase_units"][0]["payments"]["captures"][0]["id"];
        $paymentStatus = $order["purchase_units"][0]["payments"]["captures"][0]["status"];
        $currencyCode = $order["purchase_units"][0]["payments"]["captures"][0]["amount"]["currency_code"];
        $grossAmount = $order["purchase_units"][0]["payments"]["captures"][0]["seller_receivable_breakdown"]["gross_amount"]["value"];
        $fee = $order["purchase_units"][0]["payments"]["captures"][0]["seller_receivable_breakdown"]["paypal_fee"]["value"];
        $netAmount = $order["purchase_units"][0]["payments"]["captures"][0]["seller_receivable_breakdown"]["net_amount"]["value"];

        $data = ["payment_id" => $paymentId, "status" => $paymentStatus, "currency_code" => $currencyCode, "gross_amount" => $grossAmount, "fee" => $fee, "net_amount" => $netAmount];
        $model->insert($data);

        $model = new PurchaseModel();
        $purchaseId = $order["id"];
        $purchaseStatus = $order["status"];
        $createdAt = $order["purchase_units"][0]["payments"]["captures"][0]["create_time"];
        $updatedAt = $order["purchase_units"][0]["payments"]["captures"][0]["update_time"];
        $data = ["purchase_id" => $purchaseId, "user_id" => session()->get("id"), "status" => $purchaseStatus, "paypal_user_id" => $paypalUserId, "payment_id" => $paymentId, "link_id" => $linkId, "json" => json_encode($order), "created_at" => $createdAt, "updated_at" => $updatedAt];
        $model->insert($data);
        if ($paymentStatus === "COMPLETED" && $purchaseStatus === "COMPLETED") {
            $userController = new UserController();
            $userController->AddSubscription(session()->get("id"), $request["subscriptionId"], "1");
        }
        return json_encode(['success' => true, 'csrf' => csrf_hash()]);

    }
}
