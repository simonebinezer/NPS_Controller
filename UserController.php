<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Controllers\TenantController;
use App\Libraries\EmailService;
use App\Models\AccessModel;
use App\Models\PrivilegeModel;
use App\Models\SubscriptionModel;
use App\Models\UserModel;
use App\Models\TenantModel;
use DateTime;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once APPPATH . 'Libraries/TokenManagement.php';


class UserController extends BaseController
{

    public function login()
    {
        if (session()->get('id')) {

            session()->get('isLoggedIn');
            return redirect()->to(base_url('dashboard'));
        } else if ($this->request->getMethod() == 'post') {
            $rules = [
                'tenantname' => 'required',
                'email' => 'required|valid_email|CheckEmail[email]|CheckActivation[email]',
                'password' => 'required',
            ];

            $errors = [
                'email' => [
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.',
                    'CheckEmail' => 'Email Address is not registered',
                    'CheckActivation' => 'Your account is not activated, please activate it to login.'
                ],
                'tenantname' => [
                    'required' => 'The tenant name field is required.',
                ],
            ];
            if (!$this->validate($rules, $errors)) {

                $output = $this->validator->getErrors();
                return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
            }
            $rules = [
                'password' => 'required|min_length[4]|max_length[255]|validateUser[tenantname,email,password]',
            ];


            $errors = [

                'password' => [
                    'validateUser' => "Email/Tenant/Password didn't match",
                ],
            ];

            if (!$this->validate($rules, $errors)) {

                $output = $this->validator->getErrors();

                return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
            } else {
                $userModel = new UserModel();

                $user = $userModel->where('email', $this->request->getVar('email'))->first();
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', $this->request->getVar('tenantname'))->first();
                $privilege = $this->GetPrivilege($user["privilege_id"]);
                $user["accessList"] = $this->GetAccessList($privilege["access_list"]);

                $user["expiry_time"] = $privilege["expiry_date"];

                $this->setUserSession($user, $tenant);
                // Redirecting to dashboard after login
                if ($user['role'] == "admin") {

                    return json_encode(['success' => true, 'csrf' => csrf_hash()]);
                } elseif ($user['role'] == "user") {
                    return redirect()->to(base_url('user'));
                }
            }
        }
        return view('login');
    }
    public function GetAccessList($accessIdList)
    {
        $model = new AccessModel();
        $accessList = $model->whereIn("access_id", explode(",", $accessIdList))->findColumn("route");
        return $accessList;
    }
    public function GetPrivilege($privilegeId)
    {

        $model = new PrivilegeModel();
        $condition = ["privilege_id" => $privilegeId];
        $privilege = $model->where($condition)->first();
        return $privilege;
    }
    public function GetSubscriptionStatus($privilege)
    {
        $model = new SubscriptionModel();
        $condition = ["subscription_id" => $privilege["subscription_id"]];
        $subscription = $model->where($condition)->first();
        $tdyDtTm = date("d/m/Y H:i:s ");
        $subscriptionDt = new DateTime($privilege["subscription_date"]);
        $endDt = date_add($subscriptionDt, $subscription["duration"]);
        if ($tdyDtTm > $endDt) {
            return false;
        }
        return true;
    }

    public function AddSubscription($userId, $subscriptionId = "1", $subscriptionType = 1)
    {

        $model = new SubscriptionModel();
        $condition = ["subscription_id" => $subscriptionId];
        $subscription = $model->where($condition)->first();
        $currentDateTime = new DateTime();
        $subscriptionTime = $currentDateTime->format("Y-m-d H:i:s");
        $totalDuration = $subscription["duration"] * $subscriptionType;
        $currentDateTime->modify('+' . $totalDuration . 'days');

        $expiryTime = $currentDateTime->format("Y-m-d H:i:s");

        $userModel = new UserModel();
        $privilegeModel = new PrivilegeModel();
        $data = ["expiry_date" => $expiryTime];
        $data["subscription_type"] = $subscriptionType;
        $data["subscription_date"] = $subscriptionTime;
        $data["subscription_id"] = $subscriptionId;
        $privilegeId = null;
        if ($subscriptionId == "1") {
            $data["access_list"] = "1, 2, 3, 4, 5, 6, 7, 8, 9";

            $privilegeId = $privilegeModel->insert($data);
            $updateData = ["privilege_id" => $privilegeId];
            $result = $userModel->update($userId, $updateData);
        } else {
            $sessionData = ['expiry_time' => $expiryTime];
            session()->set($sessionData);
            $user = $userModel->where("id", $userId)->first();
            $result = $privilegeModel->update($user["privilege_id"], $data);
        }
        return  $privilegeId;
    }
    private function setUserSession($user, $tenant)
    {
        $data = [
            'id' => $user['id'],
            'firstname' => $user['firstname'],
            'lastname' => $user['lastname'],
            'username' => $user['username'],
            'logo_update' => $user['logo_update'],
            'status' => $user['status'],
            'phone_no' => $user['phone_no'],
            'email' => $user['email'],
            'access_list' => $user["accessList"],
            'expiry_time' => $user["expiry_time"],
            'isLoggedIn' => true,
            "role" => $user['role'],
            "tenant_id" => $tenant['tenant_id'],
            "tenant_name" => $tenant['tenant_name'],
            "survey_email" => $tenant['survey_email'],
            "db_name" => $tenant['database_name'],
            "db_host" => $tenant['host'],
            "db_username" => $tenant['username'],
            "db_password" => $tenant['password'],
            "survey_Id" => 0,
        ];

        if ($tenant['tenant_id'] > 1) {
            $data["secretKey"] = $tenant["secret_key"];
        }
        session()->set($data);
        return true;
    }
    public function logout()
    {
        session()->destroy();
        return redirect()->to('login');
    }
    public function signup()
    {
        $data = [];

        if ($this->request->getMethod() == 'post') {
            $rules = [
                'firstname' => 'required|alpha',
                'lastname' => 'required|alpha',
                'username' => 'required|min_length[6]|max_length[50]|ValidateUserName[username]',
                'tenantname' => 'required|min_length[2]|max_length[50]|validateTenant[tenantname]', //CheckTenant[tenantname]',
                'email' => 'required|min_length[6]|max_length[50]|valid_email|validateEmail[email]',
                'survey_email' => 'required|min_length[6]|max_length[50]|valid_email|validateSurveyEmail[survey_email]',
                'phone_no' => 'required|numeric|exact_length[10]',
                'password' => 'required|min_length[4]|max_length[255]',
                'confirmpassword' => 'required|min_length[4]|max_length[255]|matches[password]',
            ];
            $errors = [
                'username' => [
                    'required' => 'You must choose a username.',
                    'ValidateUserName' => 'User name is already present.'
                ],
                'email' => [
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.',
                    'validateEmail' => 'Email Address is already available',
                ],
                'tenantname' => [
                    //'CheckTenant' => 'Tenant name is not present',

                    'validateTenant' => 'Tenant name is not available.'
                ],
                'survey_email' => [
                    'required' => 'This field is required.',
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.',
                    'validateSurveyEmail' => 'Email Address is already available',
                ],
            ];
            if (!$this->validate($rules, $errors)) {
                $output = $this->validator->getErrors();
                return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
            } else {

                $tenantController = new TenantController();
                $tenantController->AddTenant($this->request->getPost());
                $emailstatus = $tenantController->sendmailforReg($this->request->getPost());

                session()->setFlashdata('response', $emailstatus);
                // $model = new TenantModel();
                // $tenant = $model->where('tenant_name', $this->request->getVar('tenantname'))->first();
                // if (!$tenant) {
                //     $TenantController = new TenantController();
                //     $tenant = $TenantController->createTenantFront($this->request->getPost());
                //     //$this->tenantInsertQuestions($tenant);
                // }
                // $userId = $this->insertUser($this->request->getPost(), $tenant);
                // if ($tenant['tenant_id'] > 1) {
                //     $this->tenantInsertUser($this->request->getPost(), $tenant, $userId);
                // }
                // $emailstatus = $this->createTemplateForMailReg($this->request->getPost(), $userId);
                // session()->setFlashdata('response', $emailstatus);
                return json_encode(['success' => true, 'csrf' => csrf_hash()]);
            }
        }
        return view('signup');
    }
    public function insertUser($postdata, $tenantdata)
    {

        $model = new UserModel();
        $data = [
            "firstname" => $postdata['firstname'],
            "lastname" => $postdata['lastname'],
            // "username" => $postdata['username'],
            "tenant_id" => $tenantdata['tenant_id'],
            "email" =>  $postdata['email'],
            "phone_no" =>  $postdata['phone_no'],
            "role" => "admin",
            "password" => password_hash($postdata['password'], PASSWORD_DEFAULT),
            "status" => "0"
        ];
        $result = $model->insertBatch([$data]);
        $db = db_connect();
        $userId = $db->insertID();
        return $userId;
    }
    public function tenantInsertUser($postdata, $tenantdata, $userId)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];

        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $data = [
            "id" => $userId,
            "firstname" => $postdata['firstname'],
            "lastname" => $postdata['lastname'],
            //"username" => $postdata['username'],
            "tenant_id" => $tenantdata['tenant_id'],
            "email" =>  $postdata['email'],
            "phone_no" =>  $postdata['phone_no'],
            "role" => "admin",
            "password" => password_hash($postdata['password'], PASSWORD_DEFAULT),
            "status" => "0"
        ];
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_insert_user = "INSERT INTO " . $dbname . ".nps_users ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";
        $db->query($new_db_insert_user);
    }
    public function tenantInsertQuestions($tenantdata)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];

        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $insert_questions =  "INSERT INTO  " . $dbname . ".nps_question_details (`question_name`, `description`, `info_details`, `other_option`, `user_id`) VALUES
                            ('How likely is it that you would recommend " . $tenantdata['tenant_name'] . " to a friend or colleague?', 'How likely is it that you would recommend " . $tenantdata['tenant_name'] . " to a friend or colleague?', 'nps', '', 1),
                            ('We\'re excited to hear that! What is working so great with us? ', 'We\'re excited to hear that! What is working so great with us? ', 'other', '[\"Order process\",\"Quality\",\"custom order\",\"24\\/7 support\",\"Return policy\"]',1),
                            ('Thank you for your feedback. Where could we improve your perception of us? ', 'Thank you for your feedback. Where could we improve your perception of us? ', 'other', '[\"Customer service\",\"Order process\",\"Quality\",\"Work hours\",\"in person visit\"]',1),
                            ('Thank you for your feedback. What could we do better?', 'Thank you for your feedback. What could we do better?', 'other', '[\"Customer service\",\"Free Shipping\",\"Stock inventory\",\"Order process\",\"Quality\"]',1)";

        $db->query($insert_questions);
        $db->close();
    }
    public function getprofile()
    {
        if (!session()->get('id')) {
            return redirect()->to(base_url('login'));
        } else {
            $model = new UserModel();
            $a = session()->get('id');
            $userdata = $model->where('id', session()->get('id'))->first();
            return view('update_profile', ["userdata" => $userdata]);
        }
    }
    public function updateprofile()
    {
        $data = [];
        if ($this->request->getMethod() == 'post') {
            $rules = [
                'firstname'=> 'required|alpha|min_length[3]|max_length[50]',
                'lastname' => 'required|alpha|min_length[3]|max_length[50]',
                'email'    => 'required|min_length[6]|max_length[50]|valid_email|validateEmail[email]',
                'phone_no' => 'required|numeric|exact_length[10]|validateContact[phone_no]'
            ];
            $errors = [
                'email'    => [
                    'valid_email'   => 'Please check the Email field. It does not appear to be valid.',
                    'validateEmail' => 'Email Address is already available',
                ],
                'phone_no'     => [
                    'validateContact' => 'Contact number is already available',
                ],
            ];
            if (!$this->validate($rules, $errors)) {
                $request = $this->request->getPost();

                $userdata = [
                    "id"        => $request['E_id'],
                    "firstname" => $request['firstname'],
                    "lastname"  => $request['lastname'],
                    "email"     => $request['email'],
                    "phone_no"  => $request['phone_no'],

                ];
                return view('update_profile', ["validation" => $this->validator, "userdata" => $userdata]);
            } else {
                $request = $this->request->getPost();
                $data = [
                    "firstname" => $request['firstname'],
                    "lastname"  => $request['lastname'],
                    "email"     =>  $request['email'],
                    "phone_no"  =>  $request['phone_no']
                ];
                $tenantController = new TenantController();
                $tenantController->UpdateUserData($data);
                session()->setFlashdata('response', "Data updated Successfully");
            }
            return redirect()->to(base_url('user/userprofile'));
        }
    }
    public function updatepassword()
    {
        $data = [];
        if ($this->request->getMethod() == 'post') {
            // $rules = [
            //     'password' => 'required|min_length[4]|max_length[255]|passwordchecker[password]',
            //     'confirmpassword' => 'required|min_length[4]|max_length[255]|matches[password]',
            // ];
            // $errors = [
            //     'password' => [
            //         'passwordchecker' => "Current password is not same as old password",
            //     ],
            // ];
            // if (!$this->validate($rules, $errors)) {
            //     return view('changepassword', [
            //         "validation" => $this->validator,
            //     ]);
            // } else {
            $data = [
                "password" => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
            ];

            $updateId = session()->get('id');
            $tenantId = session()->get('tenant_id');
            $model = new UserModel();
            $model->update($updateId, $data);
            if ($tenantId > 1) {
                $this->tenantUserPasswordUpdate($data, $tenantId, $updateId);
            }
            session()->setFlashdata('response', "Password Updated Successfully");
            return redirect()->to(base_url('changepassword'));
        }
    }
    public function tenantUserPasswordUpdate($data, $tenantId, $updateId)
    {
        if (empty(session()->get('tenant_name'))) {
            $model = new TenantModel();
            $tenant = $model->where('tenant_id', $tenantId)->first();
            $ses_ten_id = $tenant['tenant_name'];
        } else {
            $ses_ten_id = session()->get('tenant_name');
        }

        $dbname = "nps_" . $ses_ten_id;
        //new DB updation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_users` SET `password` = '" . $data["password"] . "' WHERE `nps_users`.`id` = " . $updateId;
        $db->query($new_db_update_user);
    }
    public function validatepage($id)
    {
        return view('validatepage', ["userId" => $id]);
    }
    public function activateOption($id)
    {
        $model = new UserModel();
        $usersvalidate = $model->where('id', $id)->first();
        $updateId = $usersvalidate['id'];
        $data = ["status" => 1];
        $statusupdate = $model->update($updateId, $data);
        $tenantId = $usersvalidate["tenant_id"];
        if ($tenantId > 1) {
            $this->tenantUservalidate($data, $tenantId, $updateId);
        }
        session()->setFlashdata('response', "Your account is activated.");
        return redirect()->to(base_url('login'));
    }
    public function forgot()
    {
        if ($this->request->getMethod() == 'post') {
            $token = $this->request->getVar('g-recaptcha-response');

            $captchaResult = VerifyCaptcha($token);
            if ($captchaResult->success) {
            $rules = [
                'email' => 'required|min_length[6]|max_length[50]|valid_email'
            ];

            $errors = [
                'email' => [
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.'
                ],
            ];
            if (!$this->validate($rules, $errors)) {
                return view('forgetpassword', [
                        "validation" => $this->validator,"siteKey"=>env("gcaptcha.siteKey")
                ]);
            } else {
                $model = new UserModel();
                $userData = $model->where('email', $this->request->getPost("email"))->first();
                if (!$userData) {
                    return view('forgetpassword', [
                            "valid" => 'Given Email is not available in Database.',"siteKey"=>env("gcaptcha.siteKey")
                    ]);
                }
                $updateId = $userData["id"];
                $randomKey = RandomKey(8);
                $data = ["password_key" => $randomKey];
                $model = new UserModel();
                $model->update($updateId, $data);
                $tenantId = $userData["tenant_id"];

                $emailstatus = $this->createTemplateForFPMail($this->request->getPost(), $randomKey, $userData);
                session()->setFlashdata('response', $emailstatus);
                return redirect()->to(base_url('forgot'));
            }
        }
        }
        return view("forgetpassword",["siteKey"=>env("gcaptcha.siteKey")]);
    }
    public function createTemplateForFPMail($postdata, $randomKey, $userData)
    {
        $template = view("template/email-template", ["randomKey" => $randomKey, "userdata" => $userData, "postData" => $postdata]);
        $subject = "NPS Customer || Forgot Password";
        try {

            $emailService = new EmailService();
            $from = [
                "emailId" => 'support@cxanalytix.com',
                "name" => 'CX Analytix'
            ];
            $result = $emailService->SendEmail($from, $postdata["email"], $subject, $template);
            if (!$result["response"]) {
                return "Something went wrong. Please try again." . $result["error"];
            } else {
                return "Reset password link has been sent to your email.";
            }
        } catch (Exception $ex) {
            return "Something went wrong. Please try again. " . $ex->getMessage();
            }
    }
    public function tenantUserForget($data, $tenantId, $updateId)
    {
        $model = new TenantModel();
        $tenant = $model->where('tenant_id', $tenantId)->first();
        $dbname = "nps_" . $tenant['tenant_name'];
        //new DB updation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_users` SET `password` = '" . $data["password"] . "' WHERE `nps_users`.`id` = " . $updateId;
        $db->query($new_db_update_user);
    }
    public function tenantUservalidate($data, $tenantId, $updateId)
    {
        $model = new TenantModel();
        $tenant = $model->where('tenant_id', $tenantId)->first();
        $dbname = "nps_" . $tenant['tenant_name'];
        //new DB updation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_users` SET `status` = 1, updated_at =now() WHERE `nps_users`.`id` = " . $updateId;
        $db->query($new_db_update_user);
    }
    public function resetpwd($randomKey = null)
    {
        $request = $this->request->getGet();
        $userId = $request['id'];

        $tenantId = $request['t_id'];

        $model = new UserModel();
        $userData = $model->where('id', $userId)->first();

        if (!$userData || $userData["tenant_id"] != $tenantId || $userData["password_key"] != $randomKey || empty($randomKey)) {
            $response = "Reset link was expired or invalid";
            session()->setFlashdata('response', $response);
            return redirect()->to(base_url('login'));
        }
        if ($this->request->getMethod() == 'post') {
            $rules = [
                'password' => 'required|min_length[4]|max_length[255]',
                'confirmpassword' => 'required|min_length[4]|max_length[255]|matches[password]',
            ];
            $errors = [
                'password' => [
                    'passwordchecker' => "Current password is not same as old password.",
                ],
                'confirmpassword' => [
                    'required' => "Confirm password field is required.",
                ],
            ];
            if (!$this->validate($rules, $errors)) {
                return view('resetpassword', [
                    "validation" => $this->validator,
                    "randomKey" => $randomKey
                ]);
            } else {
                $data = [
                    "password" => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
                    "password_key" => ""
                ];

                $updateId = $this->request->getPost('userId');
                $tenantId = $this->request->getPost('tenant_id');
                $model = new UserModel();
                $model->update($updateId, $data);
                if ($tenantId > 1) {
                    $tenantData["password"] = $data["password"];
                    $this->tenantUserPasswordUpdate($tenantData, $tenantId, $updateId);
                }
                $response = "Password Updated Successfully";
                session()->setFlashdata('response', $response);
                return redirect()->to(base_url('login'));
            }
        }
        return view('resetpassword', ["userdata" => $userData, "randomKey" => $randomKey]);
    }
    public function Restricted()
    {
        return view("restricted");
    }
    public function GetDecryptKey()
    {

        $tokenManagement = new TokenManagement();

        $token =   $tokenManagement->GenerateJwtToken(session()->get("secretKey"), env("secretKey"));
        return json_encode(["success" => true, "csrf" => csrf_hash(), "token" => $token]);
    }
}
