<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ExternalcontactsModel;
use App\Models\TenantModel;
use App\Models\SurveyModel;
use App\Models\AnswerListModel;
use App\Models\ModelHelper;
use App\Libraries\EnumsAndConstants\EncryptConstants;

require_once APPPATH . "Libraries/EnumsAndConstants/Constants.php";


class AnswerlistController extends BaseController
{
    private $modelHelper;
    public function __construct()
    {
        $this->modelHelper = new ModelHelper();
    }

    public function AnswerList()
    {
        $data = [];
        $defaultAnswerList = [];
        $tenantAnswerList = [];
        $answerListArray = [];
        $model = new TenantModel();
        $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();

        $model = new AnswerListModel();
        $defaultAnswerList = $model->where('status', '1')->find();
        if ($tenant['tenant_id'] > 1) {
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $query = "SELECT * FROM `" . $dbname . "`.`nps_answers_details`";
            $result = $db->query($query)->getResultArray();
            // $modelHelper = new ModelHelper();
            // $toDecFields = EncryptConstants::$answer;
            // $finalResult = $modelHelper->DecryptData($result, $toDecFields);
            $answerId2 = array();
            $answerId3 = array();
            $answerId4 = array();
            foreach ($result as $answer) {
                if ($answer["question_id"] == 2) array_push($answerId2, $answer);
                else if ($answer["question_id"] == 3) array_push($answerId3, $answer);
                else array_push($answerId4, $answer);
            }
            $tenantAnswerList = [$answerId2, $answerId3, $answerId4];
            $db->close();
        }
        $answerListArray[0] = $defaultAnswerList;
        $answerListArray[1] = $tenantAnswerList;
        array_push($data, $answerListArray);
        return $data;
    }

    public function DefaultAnswerList()
    {
        $defaultAnswerList = [];

        $model = new AnswerListModel();
        $defaultAnswerList = $model->where('status', '1')->find();

        return $defaultAnswerList;
    }
    public function TenantAnswerList()
    {
        $model = new TenantModel();
        $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
        $tenantAnswerList = null;
        $answerId2 = array();
        $answerId3 = array();
        $answerId4 = array();
        $campaign_id = session()->get("survey_Id");
        if ($campaign_id > 0) {
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $query = "SELECT * FROM `" . $dbname . "`.`nps_answers_details` WHERE `nps_answers_details`.`campaign_id`='" . $campaign_id . "'";
            $result = $db->query($query)->getResultArray();
            // $modelHelper = new ModelHelper();
            // $toDecFields = EncryptConstants::$answer;
            //   $finalResult = $modelHelper->DecryptData($result, $toDecFields);
            foreach ($result as $answer) {
                if ($answer["question_id"] == 2) array_push($answerId2, $answer);
                else if ($answer["question_id"] == 3) array_push($answerId3, $answer);
                else array_push($answerId4, $answer);
            }
            $db->close();
        }
        $tenantAnswerList = [$answerId2, $answerId3, $answerId4];
        return $tenantAnswerList;
    }

    public function AnswerList1()
    {

        $default = $this->DefaultAnswerList();
        $tenant = array();
        if (session()->get("tenant_id") > 1) {
            $tenant = $this->TenantAnswerList();
        }
        $answerList = [$default, $tenant];
        return $answerList;
    }
    public function insertAnswer($postData, $userId)
    {
        $model = new AnswerListModel();
        $data = [
            "answer_name" => $this->escapeString($postData["answer"]),
            //"description" => $this->escapeString($postData["ainfo"]),
            "created_id" => $userId,
            "status" => 1
        ];
        $result = $model->insertBatch([$data]);
        $db = db_connect();
        $questionId = $db->insertID();
        return $questionId;
    }
    public function tenantInsertAnswer($postData, $tenantdata, $userId)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $data = [
            "answer_name" => $this->escapeString($postData["answer"]),
            //"description" => $this->escapeString($postData["ainfo"]),
            "created_id" => $userId,
            "status" => 1
        ];
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_insert_user = "INSERT INTO " . $dbname . ".nps_answers_details ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";
        $db->query($new_db_insert_user);
        $db->close();
    }
    public function editAnswer($ans_id)
    {
        $model = new AnswerListModel();
        $getAnswerData = $model->where('answer_id', $ans_id)->first();

        if ($this->request->getMethod() == 'post') {
            $rules = [
                'answer' => 'required|min_length[2]|max_length[100]',
                'ainfo' => 'required|min_length[2]|max_length[100]',
            ];
            $errors = [
                'answer' => [
                    'required' => 'You must choose a answer.',
                ]
            ];

            if (!$this->validate($rules, $errors)) {

                return view('admin/editanswer', [
                    "validation" => $this->validator,
                ]);
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
                $userId = session()->get('id');
                if ($tenant['tenant_id'] == 1) {
                    $this->updateAnswer($this->request->getPost(), $ans_id);
                } else {
                    $this->tenantUpdateAnswer($this->request->getPost(), $tenant, $ans_id);
                }
                session()->setFlashdata('response', "Update Answer Successfully");
                return redirect()->to(base_url('answerList'));
            }
        }
        return view('admin/editanswer',  ["getQuestData" => $getAnswerData]);
    }
    public function updateAnswer($postData, $answerId)
    {
        $model = new AnswerListModel();
        $data = [
            "answer_name" => $this->escapeString($postData["answer"]),
            "description" => $this->escapeString($postData["ainfo"])
        ];
        $model->update($answerId, $data);
    }

    public function tenantUpdateAnswer($postData, $tenantdata, $qid)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $cols = array();
        $data = [
            "answer_id" => $qid,
            "answer_name" => $this->escapeString($postData["answer"]),
            "description" => $this->escapeString($postData["ainfo"])
        ];
        foreach ($data as $key => $val) {
            $cols[] = "$key = '$val'";
        }

        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_answers_details` SET " . implode(', ', $cols) . " WHERE `nps_answers_details`.`answer_id` = " . $qid;
        $db->query($new_db_update_user);
        $db->close();
    }
    public function deleteAnswer($ans_id)
    {

        $modeldel = new TenantModel();
        $tenant = $modeldel->where('tenant_name', session()->get('tenant_name'))->first();

        if ($tenant['tenant_id'] == 1) {
            $model = new AnswerListModel();
            $model->where('answer_id', $ans_id)->delete();
        } else {
            $this->tenantDeleteAnswer($tenant, $ans_id);
        }
        session()->setFlashdata('response', "Answer deleted Successfully");
        return redirect()->to(base_url('answerList'));
    }
    public function tenantDeleteAnswer($tenantdata, $ans_id)
    {
        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $delete_query  = "DELETE FROM " . $dbname . ".`nps_answers_details` WHERE `nps_answers_details`.`answer_id` =" . $ans_id;
        $db->query($delete_query);
        $db->close();
    }
    public function createAnswer1()
    {
        if ($this->request->getMethod() == 'post') {
            $rules = [
                'answer_name' => 'required|min_length[5]|max_length[20]|validate_answer[answer_name]',
                //'ainfo' => 'required|min_length[2]|max_length[100]',
            ];
            $errors = [
                'answer_name' => [
                    'required' => 'You must choose a answer.',
                    'min_length' => 'Please enter atleast 5 characters.',
                    'max_length' => 'Please enter less than 20  characters.',
                    'validate_answer' => "Answer already present, please check."
                ]
            ];

            if (!$this->validate($rules, $errors)) {
                $output = $this->validator->getError('answer_name');
                echo json_encode(['success' => false, 'csrf' => csrf_hash(), 'data' => $output]);
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
                $userId = session()->get('id');
                $postData = $this->request->getPost();
                if ($tenant['tenant_id'] == 1) {
                    $output = $this->insertAnswer1($postData, $userId);
                } else {
                    $output = $this->tenantInsertAnswer1($postData, $tenant, $userId);
                }
                if ($output) {
                    echo json_encode(['success' => true, 'csrf' => csrf_hash(), 'data' => $output]);
                } else {
                    $output = "Answer limit of 20 exceeded.";
                    echo json_encode(['success' => false, 'csrf' => csrf_hash(), 'data' => $output]);
                }
            }
        }
    }
    public function insertAnswer1($postData, $userId)
    {
        $data = [
            "answer_name" => $this->escapeString($postData["answer_name"]),
            "created_id" => $userId,
            "status" => '1'
        ];
        $output = null;

        $model = new AnswerListModel();
        $answerId = $model->selectMax('answer_id')->first();
        if (($answerId != null && $answerId["answer_id"] < 20) || $answerId == null) {
            $model = new AnswerListModel();

            $result = $model->insertBatch([$data]);
            $db = db_connect();
            $answerId = $db->insertID();
            $output = [$answerId => $data["answer_name"]];
        } else {
            $model = new AnswerListModel();
            $inactiveAnswer = $model->where('status', '0')->first();
            if ($inactiveAnswer != null && count($inactiveAnswer) > 0) {
                $model = new AnswerListModel();

                $model->update($inactiveAnswer["answer_id"], $data);
                $output = [$inactiveAnswer["answer_id"] => $data["answer_name"]];
            } else $output = false;
        }
        return $output;
    }
    public function tenantInsertAnswer1($postData, $tenantdata, $userId)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $a = session()->get("survey_Id");
        if (session()->get("survey_Id") == 0) {
            $model = new SurveyModel();
            $lastSurveyId = $model->selectMax('campaign_id')->first();
            $lastSurveyId = $lastSurveyId ? $lastSurveyId : 0;
            $surveydata = [
                "campaign_name" => "",
                "placeholder_name" => "",
                "question_id_1" => 1,
                "question_id_2" => 2,
                "answer_id_2" => "",
                "question_id_3" => 3,
                "answer_id_3" => "",
                "question_id_4" => 4,
                "answer_id_4" => "",
                "user_id" => $userId
            ];
            $model->insert($surveydata);
            $lastSurveyId = $model->getInsertID();
            $data = ["survey_Id" => $lastSurveyId];
            session()->set($data);
        }

        $data = [
            "answer_name" => $this->escapeString($postData["answer_name"]),
            //"description" => $this->escapeString($postData["ainfo"]),
            "created_id" => $userId,
            "question_id" => $postData["question_Id"],
            "status" => 1,
            "campaign_id" => session()->get("survey_Id")
        ];
        $answerModel = new AnswerListModel();
        // $toEncFields = EncryptConstants::$answer;
        //$this->modelHelper->InsertData($answerModel, $data, $toEncFields);
        $answerModel->insert($data);
        // $key = array_keys($data);
        // $values = array_values($data);
        // $new_db_insert_user = "INSERT INTO " . $dbname . ".nps_answers_details ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";
        // $db->query($new_db_insert_user);
        $answerId = $db->insertID();
        $db->close();

        $output = [$answerId => $data["answer_name"]];

        return $output;
    }

    public function editAnswer1()
    {

        if ($this->request->getMethod() == 'post') {
            $rules = [
                'answer_name' => 'required|min_length[5]|max_length[20]|validate_answer[answer_name]',
                //'ainfo' => 'required|min_length[2]|max_length[100]',
            ];
            $errors = [
                'answer_name' => [
                    'required' => 'You must choose a answer.',
                    'min_length' => 'Please enter atleast 5 characters',
                    'max_length' => 'Please enter less than 20  characters',
                    'validate_answer' => "Answer already present, please check."
                ]
            ];

            if (!$this->validate($rules, $errors)) {
                $output = $this->validator->getError('answer_name');
                // return view('admin/editanswer', [
                //     "validation" => $this->validator,
                // ]);
                //$output = "answer already present, please check.";
                echo json_encode(['success' => false, 'csrf' => csrf_hash(), 'data' => $output]);
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
                $userId = session()->get('id');
                $postData = $this->request->getPost();
                $answer_id = ($postData["answer_id"]);

                $data = [
                    "answer_name" => $this->escapeString($postData["answer_name"]),
                    //"description" => $this->escapeString($postData["ainfo"]),
                    "created_id" => $userId,
                ];
                if ($tenant['tenant_id'] == 1) {
                    $model = new AnswerListModel();
                    $model->update($answer_id, $data);
                } else {
                    $this->tenantUpdateAnswer1($this->request->getPost(), $tenant, $answer_id);
                }
                //session()->setFlashdata('response',"Update Answer Successfully");
                echo json_encode(['success' => true, 'csrf' => csrf_hash()]);
            }
        }
    }

    public function tenantUpdateAnswer1($data, $tenantdata, $answer_id)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $cols = array();
        $model = new AnswerListModel();
       // $toEncFields = EncryptConstants::$answer;
        //$this->modelHelper->UpdateData($model, $answer_id, $data, $toEncFields);
        // foreach ($data as $key => $val) {
        //     $cols[] = "$key = '$val'";
        // }
        // $new_db_update_user = "UPDATE  " . $dbname . ".`nps_answers_details` SET " . implode(', ', $cols) . " WHERE `nps_answers_details`.`answer_id` = " . $answer_id;
        // $db->query($new_db_update_user);
        $model->update($answer_id, $data);
        $db->close();
    }


    public function deleteAnswer1()
    {
        $ans_id = $this->request->getPost();
        $modeldel = new TenantModel();
        $tenant = $modeldel->where('tenant_name', session()->get('tenant_name'))->first();

        if ($tenant['tenant_id'] == 1) {
            $model = new AnswerListModel();
            // $model->where('answer_id', $ans_id["ans"])->delete();  
            $model->update($ans_id["ans"], ["status" => '0']);
        } else {
            $this->tenantDeleteAnswer1($tenant, $ans_id["ans"]);
        }
        echo json_encode(['success' => true, 'csrf' => csrf_hash()]);
    }
    public function tenantDeleteAnswer1($tenantdata, $ans_id)
    {
        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $delete_query  = "DELETE FROM " . $dbname . ".`nps_answers_details` WHERE `nps_answers_details`.`answer_id` =" . $ans_id;
        $db->query($delete_query);
        $db->close();
    }


    public function DeleteTempRecords()
    {
        $tenantId = session()->get('tenant_id');
        if ($tenantId > 1 && session()->get("survey_Id") > 0) {
            $campaign_id = session()->get("survey_Id");
            $model = new TenantModel();
            $tenantdata = $model->where('tenant_name', session()->get('tenant_name'))->first();

            $dbname = "nps_" . $tenantdata['tenant_name'];
            $db = db_connect();
            $db->query('USE ' . $dbname);

            $model = new SurveyModel();
            $condition = ["campaign_id" => $campaign_id];
            $survey = $model->where($condition)->first();
            if ($survey["campaign_name"] == "") {
                $model->delete($campaign_id);

                $model = new AnswerListModel();
                $model->where('campaign_id', $campaign_id)->delete();
            }
            $db->close();
            $data = ["survey_Id" => 0];
            session()->set($data);
        }
        return null;
    }

    public function GetPreviousAnswers()
    {
        $postData = $this->request->getPost();
        $model = new TenantModel();
        $tenantData = $model->where('tenant_name', session()->get('tenant_name'))->first();
        $dbname = "nps_" . $tenantData['tenant_name'];
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $model = new AnswerListModel();

        if (session()->get("survey_Id") == 0) {
            $this->GetSurveyId();
        }
        $campaign_id = session()->get("survey_Id");
        $query = "SELECT DISTINCT answer_name FROM `nps_answers_details` WHERE question_id='" . $postData['question_Id'] . "'  AND answer_name NOT IN( SELECT answer_name from `nps_answers_details` WHERE question_id='" . $postData['question_Id'] . "' AND campaign_id='" . $campaign_id . "')";
        $answerList = $db->query($query)->getResultArray();
        $insertedList = array();
        if (count($answerList) > 0) {
            // if (session()->get("survey_Id") == 0) {
            //     $this->GetSurveyId();
            // }
            //$answerList = $this->modelHelper->DecryptData($answerList, EncryptConstants::$answer);

            $insertedList =  $this->InsertBulkAnswers($answerList, $postData['question_Id']);
        }
        $db->close();
        echo json_encode(['success' => true, 'csrf' => csrf_hash(), 'answerList' => $insertedList]);
    }

    public function InsertBulkAnswers($uniqueAnswers, $question_Id)
    {

        $model = new AnswerListModel();
        $campagn_id = session()->get('survey_Id');
        $created_id = session()->get('id');

        $answers = array();
        $count = 0;
        $insertedList = array();
        foreach ($uniqueAnswers as $key => $value) {
            $answers['answer_name'] = $value["answer_name"];
            $answers['campaign_id'] = $campagn_id;
            $answers['question_id'] = $question_Id;
            $answers['created_id'] = $created_id;

           // $this->modelHelper->InsertData($model, $answers, EncryptConstants::$answer);
            $model->insert($answers);
            
            $temp = $model->getInsertID();
            $insertedList[$temp] = $value['answer_name'];
        }

        return $insertedList;
    }

    public function GetSurveyId()
    {
        $model = new SurveyModel();
        $lastSurveyId = $model->selectMax('campaign_id')->first();
        $lastSurveyId = $lastSurveyId ? $lastSurveyId : 0;
        $surveydata = [
            "campaign_name" => "",
            "placeholder_name" => "",
            "question_id_1" => 1,
            "question_id_2" => 2,
            "answer_id_2" => "",
            "question_id_3" => 3,
            "answer_id_3" => "",
            "question_id_4" => 4,
            "answer_id_4" => "",
            "user_id" => session()->get('id')
        ];
        $model->insert($surveydata);
        $lastSurveyId = $model->getInsertID();
        $data = ["survey_Id" => $lastSurveyId];
        session()->set($data);
    }
    public function escapeString($val)
    {
        $db = db_connect();
        $connectionId = $db->connID;
        $val = mysqli_real_escape_string($connectionId, $val);
        return $val;
    }


    public function EncryptData()
    {

        $tables = [[new SurveyModel(), EncryptConstants::$survey], [new ExternalcontactsModel(), EncryptConstants::$customer]];

        $this->GetEncryptedDataFromTables($tables);
    }


    public function GetEncryptedDataFromTables(array $tables)
    {
        $encryptedData = [];

        foreach ($tables as $key => $table) {

            $this->modelHelper->EncryptAllData($table);
        }

        return $encryptedData;
    }
}
