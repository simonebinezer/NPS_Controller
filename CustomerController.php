<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\EnumsAndConstants\EncryptConstants;
use App\Models\ExternalcontactsModel;
use App\Models\ModelHelper;
use App\Models\TagMapModel;
use App\Models\TagModel;
use App\Models\SegmentModel;
use App\Models\SegmentTagModel;
use App\Models\TagMapViewModel;
use App\Models\SegmentTagViewModel;
use App\Models\SegmentMailsViewModel;
use App\Models\TenantModel;

require_once APPPATH . "Libraries/EnumsAndConstants/Constants.php";

class CustomerController extends BaseController
{
    private $modelHelper;
    public function __construct()
    {

        if (session()->get("tenant_id") > 1) {
            $this->modelHelper = new ModelHelper();
        }
    }

    public function UpdateCustomerDetails()
    {
        $data = [];
        $postData = $this->request->getPost();

        if ($this->request->getMethod() == 'post') {
            $rules = [
                'first_name' => 'required|alpha',
                'last_name' => 'required|alpha',
                'contact' => 'required|numeric|exact_length[10]|validateCustomerContact[contact]',
                'email' => 'required|min_length[6]|max_length[50]|valid_email|validateCustomerEmail[email]'
            ];
            $errors = [

                'first_name' => [
                    'required' => 'First Name is required.',
                ],
                'last_name' => [
                    'required' => 'Last Name is required.',
                ],
                'contact' =>
                [
                    'validateCustomerContact' => 'Contact is already available.'
                ],

                'email' => [
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.',
                    'validateCustomerEmail' => 'Email Address is already available.',
                ],

            ];
            if (!$this->validate($rules, $errors)) {
                $userslist = $this->GetCustomerList();
                $a = $this->validator->getErrors();
                return view('admin/getCustomerlist', [
                    "validation" => $this->validator,
                    "Function" => "EDIT",
                    "Data" => json_encode($postData),
                    ["userslist" => $userslist]
                ]);
            } else {

                $postData = $this->request->getPost();
                $model = new ExternalcontactsModel();
                $data = [
                    "id" => $postData['E_Id'],
                    "name" => $postData['first_name'] . " " . $postData['last_name'],
                    "firstname" => $postData['first_name'],
                    "lastname" => $postData['last_name'],
                    "email_id" =>  $postData['email'],
                    "contact_details" =>  $postData['contact'],
                    "created_by" => session()->get('id')
                ];
                if (session()->get('tenant_id') == 1) {
                    $result = $model->update($data['id'], $data);
                } else {

                    $model = new TenantModel();
                    $tenant = $model->where('tenant_id', session()->get('tenant_id'))->first();
                    $dbname = "nps_" . $tenant['tenant_name'];
                    //new DB creation for Tenant details
                    $db = db_connect();
                    $db->query('USE ' . $dbname);
                    // foreach ($data as $key => $val) {
                    //     $cols[] = "$key = '$val'";
                    // }
                    // $query = "UPDATE  " . $dbname . ".nps_external_contacts SET " . implode(',', $cols) . " WHERE nps_external_contacts.id = '" . $data['id'] . "'";
                    // $db->query($query);
                    $model = new ExternalcontactsModel();
                    $this->modelHelper->UpdateData($model, $data['id'], $data, EncryptConstants::$customer);
                    $db->close();
                }
                return redirect()->to(base_url('customer/getCustomerData'));
            }
        }
    }

    public function InsertCustomerDetails()
    {
        if ($this->request->getMethod() == 'post') {
            $postData = $this->request->getPost();
            $rules = [
                'first_name' => 'required|alpha',
                'last_name' => 'required|alpha',
                'contact' => 'required|numeric|exact_length[10]|validateCustomerContact[contact]',
                'email' => 'required|min_length[6]|max_length[50]|valid_email|validateCustomerEmail[email]'
            ];
            $errors = [

                'first_name' => [
                    'required' => 'First Name is required.',
                ],
                'last_name' => [
                    'required' => 'Last Name is required.',
                ],
                'contact' =>
                [
                    'validateCustomerContact' => 'Contact is already available'
                ],
                'email' =>
                [
                    'valid_email' => 'Please check the Email field. It does not appear to be valid.',
                    'validateCustomerEmail' => 'Email Address is already available.',
                ],

            ];
            if (!$this->validate($rules, $errors)) {
                $userlist = $this->GetCustomerList();
                return view('admin/getCustomerlist', [
                    "validation" => $this->validator,
                    "Function" => "ADD",
                    "Data" => json_encode($postData),
                    ["userslist" => $userlist]
                ]);
            } else {

                $model = new ExternalcontactsModel();
                $data = [
                    "name" => $postData['first_name'] . " " . $postData['last_name'],
                    "firstname" => $postData['first_name'],
                    "lastname" => $postData['last_name'],
                    "email_id" =>  $postData['email'],
                    "contact_details" =>  $postData['contact'],
                    "created_by" => session()->get('id')
                ];
                if (session()->get('tenant_id') == 1) {
                    $result = $model->insert($data, true);
                }
                #$db = db_connect();        
                #$userId = $db->insertID();
                else {
                    $this->tenantCreateContact($data);
                }
                return redirect()->to(base_url('customer/getCustomerData'));
            }
        }
    }

    public function DeleteCustomer()
    {
        $postData = $this->request->getPost();
        $model = new ExternalcontactsModel();
        $data = ['status' => '0'];
        if (session()->get('tenant_id') == 1) {
            $result = $model->update($postData['Id'], $data);
        } else {
            $model = new TenantModel();
            $tenant = $model->where('tenant_id', session()->get('tenant_id'))->first();
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $db->query('USE ' . $dbname);
            $query = "UPDATE  " . $dbname . ".nps_external_contacts SET nps_external_contacts.status = '" . $data['status'] . "' WHERE nps_external_contacts.id = '" . $postData['Id'] . "'";
            $db->query($query);
            $db->close();
        }
        return redirect()->to(base_url('customer/getCustomerData'));
    }

    public function UploadFileCustomer()
    {
        $rules = [
            'formData' => 'uploaded[formData]|max_size[formData,2048]|ext_in[formData,csv]'
        ];
        $errors =
            [
                'formData' =>
                [
                    'max_size' => 'Uploaded file size is more than 2mb',
                    'ext_in' => "Uploaded file is not a csv file"
                ]
            ];
        $input = $this->validate($rules, $errors);
        if (!$input) {
            $data = $this->validator->getErrors();
            echo json_encode(['success' => false, 'validation' => $data, 'csrf' => csrf_hash()]);
        } else {
            if ($file = $this->request->getFile('formData')) {
                if ($file->isValid() && !$file->hasMoved()) {
                    $newName = $file->getRandomName();
                    $file->move('../public/csvfile', $newName);
                    $file = fopen("../public/csvfile/" . $newName, "r");
                    $i = 0;
                    $numberOfFields = 4;
                    $csvArr = array();
                    while (($filedata = fgetcsv($file, 1000, ",")) !== FALSE) {
                        $num = count($filedata);
                        if ($i > 0 && $num == $numberOfFields) {
                            if (!contains_special_characters($filedata[0] . $filedata[1])) {
                                $csvArr[$i]['name'] = $filedata[0] . " " . $filedata[1];
                                $csvArr[$i]['firstname'] = $filedata[0];
                                $csvArr[$i]['lastname'] = $filedata[1];
                                $csvArr[$i]['contact'] = $filedata[2];
                                $csvArr[$i]['email'] = $filedata[3];
                            }
                        }
                        $i++;
                    }
                    fclose($file);
                    $count = 0;
                    $emaillist = array();
                    foreach ($csvArr as $exportData) {
                        $validateEmail = $this->Check_ContactAndEmail($exportData["email"], $exportData["contact"]);
                        if ($validateEmail) {
                            $tenant  =
                                [
                                    "tenant_id" => session()->get('tenant_id'),
                                    "tenant_name" =>  session()->get('tenant_name')
                                ];
                            $data =
                                [
                                    "name" => $exportData["name"],
                                    "firstname" => $exportData["firstname"],
                                    "lastname" => $exportData["lastname"],
                                    "contact_details" => $exportData["contact"],
                                    "email_id" => $exportData["email"],
                                    "created_by" => session()->get('id')
                                ];
                            if ($tenant['tenant_id'] == 1) {
                                $this->createContact($data);
                            } else {
                                $this->tenantCreateContact($data);
                            }
                            $count++;
                        }
                    }
                }
            }
            echo json_encode(['success' => true, 'csrf' => csrf_hash(), "count" =>  $count]);
        }
    }

    public function Check_ContactAndEmail(string $emailid, $contact)
    {
        if (session()->get('tenant_id') > 1) {
            $dbname = "nps_" . session()->get('tenant_name');
            //new DB creation for Tenant details
            $db = db_connect();
            $db->query('USE ' . $dbname);
            //$new_db_select = "SELECT * FROM " . $dbname . ".nps_external_contacts  WHERE (`nps_external_contacts`.`email_id` = '" . $emailid . "' OR nps_external_contacts.contact_details = '" . $contact . "') and nps_external_contacts.status = " . 1;


            // $result = $db->query($new_db_select);
            $condition = ['status' => '1'];
            $model = new ExternalcontactsModel();
            $result = $this->modelHelper->GetAllData($model, $condition, EncryptConstants::$customer);

            $filters = ["email_id" => $emailid, "contact_details" => $contact];
            $filterData = Arrayfilter($result, $filters);
            if (count($filterData) > 0) {
                return false;
            }
            return true;
        } else {
            $model = new ExternalcontactsModel();
            $contactlist =  $model->where("status='1' AND (email_id='$emailid' OR contact_details= $contact)")->first();
            if ($contactlist) {
                return false;
            }
            return true;
        }
    }
    public function createContact($exportData)
    {
        $model = new ExternalcontactsModel();
        $result = $model->insertBatch([$exportData]);
    }

    public function tenantCreateContact($data)
    {
        $tenant  =
            [
                "tenant_id" => session()->get('tenant_id'),
                "tenant_name" =>  session()->get('tenant_name')
            ];

        $dbname = "nps_" . $tenant['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $model = new ExternalcontactsModel();
        $condition = ["email_id" => $data["email_id"], "status" => '0'];
        // $query = "SELECT * FROM " . $dbname . ".`nps_external_contacts` WHERE `nps_external_contacts`.`email_id`='" . $data["email_id"] . "' AND `nps_external_contacts`.`status`='0'";
        //$result = $db->query($query);
        $result = $this->modelHelper->GetSingleData($model, $condition, EncryptConstants::$customer);
        if ($result) {

            // foreach ($data as $key => $val) {
            //     $cols[] = "$key = '$val'";
            // }
            //$query = "UPDATE  " . $dbname . ".nps_external_contacts SET nps_external_contacts.status = '1', " . implode(',', $cols) . "WHERE `nps_external_contacts`.`email_id` = '" . $data['email_id'] . "'";
            //$db->query($query);
            $updateData = ["status" => "1"];

            $result = $this->modelHelper->UpdateData($model, $result["id"], $updateData, EncryptConstants::$customer);
        } else {
            // $key = array_keys($data);
            // $values = array_values($data);
            // $query = "INSERT INTO " . $dbname . ".nps_external_contacts  ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";
            // $db->query($query);
            $this->modelHelper->InsertData($model, $data, EncryptConstants::$customer);
        }
        $db->close();
        return true;
    }

    public function MapTag()
    {
        $data = [];
        $request = $this->request->getPost();
        $this->ConnectDB();
        //$data=['tag_id'=>$request['tagList'], 'customer_id'=>$request['customerId']];
        $customerId = $request['customerId'];

        if (array_key_exists('tagList', $request)) {
            $tagList = $request['tagList'];
            for ($i = 0; $i < count($tagList); $i++) {
                # code...
                $data[$i]['tag_id'] = $tagList[$i];
                $data[$i]['customer_id'] = $customerId;
            }
        }
        $model = new TagMapModel();
        $model->where('customer_id', $customerId)->delete();
        if (count($data) > 0) {
            $model->insertBatch($data);
        }
        return redirect()->to(base_url('customer/getCustomerData'));
    }

    private function ConnectDB()
    {
        $model = new TenantModel();
        $tenant = $model->where('tenant_id', session()->get('tenant_id'))->first();
        $dbname = "nps_" . $tenant['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
    }
    public function CreateTag()
    {

        $request = $this->request->getPost();
        $this->ConnectDB();
        $rules = [
            'tag_name' => 'required|ValidateTagName[tag_name]',

        ];
        $errors = [

            'tag_name' => [
                'required'            => 'Tag name is required.',
                'ValidateTagName' => 'Tag name is already present.'
            ]

        ];
        if (!$this->validate($rules, $errors)) {
            $output = $this->validator->getErrors();
            return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
        } else {
            $data = ['tag_name' => strtoupper(trim($request['tag_name'])), 'created_by' => session()->get('id')];
            $model = new TagModel();
            $model->insert($data);
            return json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }
    public function EditTag()
    {
        $request = $this->request->getPost();

        $this->ConnectDB();
        $rules = [
            'E_tag_name' => 'required|ValidateTagName[E_tag_name]',

        ];
        $errors = [

            'E_tag_name' => [
                'required'            => 'Tag name is required.',
                'ValidateTagName' => 'Tag name is already present.'
            ]

        ];
        if (!$this->validate($rules, $errors)) {
            $output = $this->validator->getErrors();
            return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
        } else {
            $data = ['tag_name' => strtoupper(trim($request['E_tag_name'])), 'created_by' => session()->get('id')];
            $tagId = $request['E_tag_id'];
            $model = new TagModel();
            $model->update($tagId, $data);
            return json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }

    public function DeleteTag()
    {
        $request = $this->request->getPost();
        $this->ConnectDB();
        $data = ['tag_id' => $request['tag_id']];
        $model = new TagModel();
        $model->delete($data);
        return json_encode(['success' => true, 'csrf' => csrf_hash()]);
    }

    public function GetTags()
    {
        $model = new TagModel();
        $tags = $model->findAll();
        return $tags;
    }
    public function GetCustomerList()
    {

        if (session()->get('tenant_id') > 1) {
            $this->ConnectDB();
        }
        $model = new TagMapViewModel();
        $userslist = $this->modelHelper->GetAllData($model, null, EncryptConstants::$customer);
        $tagList = $this->GetTags();
        return view('admin/getCustomerlist', ["userslist" => $userslist, "tagList" => $tagList]);
        //}
    }

    public function CreateSegment()
    {

        $request = $this->request->getPost();
        $this->ConnectDB();
        $rules = [
            'segment_name' => 'required|ValidateSegmentName[segment_name]',

        ];
        $errors = [

            'segment_name' => [
                'required'            => 'Segment name is required.',
                'ValidateSegmentName' => 'Segment name is already present.'
            ]

        ];
        if (!$this->validate($rules, $errors)) {
            $output = $this->validator->getErrors();
            return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
        } else {
            //$tagList = $request['tagList'];
            $data = ['segment_name' => strtoupper(trim($request['segment_name'])), 'created_by' => session()->get('id')];
            //$data['tag_list'] = implode(",",$tagList);
            $model = new SegmentModel();
            $model->insert($data);

            return json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }
    public function EditSegment()
    {
        $request = $this->request->getPost();
        $this->ConnectDB();
        $rules = [
            'E_segment_name' => 'required|ValidateSegmentName[segment_name]',

        ];
        $errors = [

            'E_segment_name' => [
                'required'            => 'Segment name is required.',
                'ValidateSegmentName' => 'Segment name is already present.'
            ]

        ];
        if (!$this->validate($rules, $errors)) {
            $output = $this->validator->getErrors();
            return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
        } else {
            //$tagList = $request['E_tagList'];
            //$data['tag_list'] = implode(",",$tagList);
            $data = ['segment_name' => strtoupper(trim($request['E_segment_name'])), 'updated_by' => session()->get('id')];
            $segmentId = $request['E_segment_id'];
            $model = new SegmentModel();
            $model->update($segmentId, $data);
            return json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }

    public function DeleteSegment()
    {
        $request = $this->request->getPost();
        $this->ConnectDB();
        $data = ['segment_id' => $request['segment_id']];
        $model = new SegmentModel();
        $model->delete($data);
        return json_encode(['success' => true, 'csrf' => csrf_hash()]);
    }
    public function GetSegments($search)
    {
        $model = new SegmentMailsViewModel();
        $segments = $model->like('segment_name', $search)->findAll();
        return $segments;
    }

    public function AddTagWithSegment()
    {

        $request = $this->request->getPost();

        $rules = [
            'tagList' => 'required'
        ];
        $errors = [

            'tagList' => [
                'required'   => 'Please choose atleast one tag.'
            ]

        ];
        if (!$this->validate($rules, $errors)) {
            $output = $this->validator->getErrors();
            return json_encode(['success' => false, 'csrf' => csrf_hash(), 'error' => $output]);
        } else {
            $this->ConnectDB();

            $tagList = $request['tagList'];
            $segmentId = $request['segmentId'];
            for ($i = 0; $i < count($tagList); $i++) {

                $data[$i]['tag_id'] = $tagList[$i];
                $data[$i]['segment_id'] = $segmentId;
            }
            $model = new SegmentTagModel();
            $model->where('segment_id', $segmentId)->delete();
            $model->insertBatch($data);
            return json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }
    public function GetSegmentsAndTags()
    {

        if (session()->get('tenant_id') > 1) {
            $this->ConnectDB();
        }
        $tagList = $this->GetTags();
        $model = new SegmentTagViewModel();
        $segmentList = $model->findAll();

        return view("admin/segmentsAndTags", ["segmentList" => $segmentList, "tagList" => $tagList]);
    }

    public function GetEmailsFromSegments($segmentIdList)
    {
        $segmentMailList = [];
        if (count($segmentIdList)) {

            $model = new SegmentMailsViewModel();
            $result = $model->whereIn('segment_id', $segmentIdList)->findAll();

            foreach ($result as $key => $value) {

                $customerIdList =  explode(",", $value['customer_id_list']);
                $segmentMailList = array_merge($segmentMailList, $customerIdList);
            }
        }
        return $segmentMailList;
    }
}
