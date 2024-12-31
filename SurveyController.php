<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Libraries\EnumsAndConstants\EncryptConstants;
use App\Models\ModelHelper;
use App\Models\TenantModel;
use App\Models\SurveyModel;


require_once APPPATH."Libraries/EnumsAndConstants/Constants.php";
class SurveyController extends BaseController
{
    private $modelHelper;

    public function __construct()
    {
        $this->modelHelper = new ModelHelper();
    }
    public function index()
    {
        $reset = ["survey_Id" => 0];
        session()->set($reset);
        $QuestionListController = new QandAController();

        $questionList = $QuestionListController->QuestionList1();
        $tenantData = $this->GetTenantData();
        $AnswerlistController = new AnswerlistController;

        $answerList = $AnswerlistController->AnswerList1();

        $optionsCountArray = [5, 7];
        $answerLimit = [5, 20];
        $tenantData = $this->GetTenantData();
        return view('admin/CreateSurvey', ["getQuestData" =>  $questionList, "answerList" => $answerList, "tenantData" => $tenantData, "optionsCount" => $optionsCountArray, "answerLimit" => $answerLimit]);
    }
    public function GetTenantData()
    {
        $model = new TenantModel();
        $tenantData = $model->where('tenant_name', session()->get('tenant_name'))->first();
        return $tenantData;
    }

    public function createSurvey()
    {

        $QuestionListController = new QandAController();

        $questionList = $QuestionListController->QuestionList1();
        $tenantData = $this->GetTenantData();
        if ($this->request->getMethod() == 'post') {
            //     $rules = [
            //         'campaign_name' => 'required|min_length[2]|max_length[200]',
            //     ];
            //     $errors = [
            //         'campaign_name' => [
            //             'required' => 'You must choose a campaign name.',
            //         ]
            //     ];

            // if (!$this->validate($rules, $errors)) {

            //     return view('admin/CreateSurvey', [
            //         "validation" => $this->validator, "getQuestData" => $questionList
            //     ]);
            // } else {
            // $model = new TenantModel();
            // $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
            $userId = session()->get('id');
            $postData = $this->request->getPost();
            $data = [
                "campaign_name" => $this->escapeString($postData["campaign_name"]),
                "placeholder_name" => $this->escapeString($postData["placeholder_name"]),
                "question_id_1" => 1,
                "question_id_2" => 2,
                "answer_id_2" => implode(',', $postData["ans_2"]),
                "question_id_3" => 3,
                "answer_id_3" => implode(',', $postData["ans_3"]),
                "question_id_4" => 4,
                "answer_id_4" => implode(',', $postData["ans_4"]),
                "user_id" => $userId
            ];
            $toEncFields = EncryptConstants::$survey;
            if ($tenantData['tenant_id'] == 1) {
                $surv_id = $this->insertSurvey($data, $toEncFields, $userId);
            } else {
                $this->tenantInsertSurvey($data, $toEncFields, $tenantData, $userId);
            }
            $reset = ["survey_Id" => 0];
            session()->set($reset);
            session()->setFlashdata('response', "A new survey was created successfully!");
            echo json_encode(['success' => true, 'csrf' => csrf_hash()]);
        }
    }
    public function insertSurvey($data, $toEncFields, $userId)
    {
        $model = new SurveyModel();
        $this->modelHelper->InsertData($model, $data,  $toEncFields);
        // $result = $model->insertBatch([$data]);
        $db = db_connect();
        $surv_id = $db->insertID();
        return $surv_id;
    }
    public function tenantInsertSurvey($data, $toEncFields, $tenantdata, $userId)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);

        $surveyId = session()->get("survey_Id");
        if ($surveyId == 0) {
            // $model = new SurveyModel();
            // $key = array_keys($data);
            // $values = array_values($data);
            // $query = "INSERT INTO " . $dbname . ".nps_survey_details ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";

            $this->insertSurvey($data, $toEncFields, $userId);
        } else {
            // $cols = array();

            // foreach ($data as $key => $val) {
            //     $cols[] = "$key = '$val'";
            // }
            //$query = "UPDATE  " . $dbname . ".`nps_survey_details` SET " . implode(', ', $cols) . " WHERE `nps_survey_details`.`campaign_id` = " . $surveyId;

            $model = new SurveyModel();
            $this->modelHelper->UpdateData($model, $surveyId, $data, $toEncFields);
        }
        // $db->query($query);
        $db->close();
        return true;
    }
    public function editsurvey($surv_id)
    {
        $getSurveyData = $this->getSurvey($surv_id);
        $QuestionListController = new QandAController();

        $questionList = $QuestionListController->QuestionList1();
        $AnswerlistController = new AnswerlistController;


        $defaultAnswerList = $AnswerlistController->DefaultAnswerList();
        $reset = ["survey_Id" => $surv_id];
        session()->set($reset);
        $a = session()->get("survey_Id");
        $tenantAnswerList = $AnswerlistController->TenantAnswerList();

        $answerList = [$defaultAnswerList, $tenantAnswerList];
        $tenantData = $this->GetTenantData();
        $optionsCountArray = [5, 7];
        $answerLimit = [5, 20];
        if ($this->request->getMethod() == 'post') {
            // $rules = [
            //     'campaign_name' => 'required|min_length[2]|max_length[200]',
            // ];
            // $errors = [
            //     'campaign_name' => [
            //         'required' => 'You must choose a campaign_name.',
            //     ]
            // ];

            // if (!$this->validate($rules, $errors)) 
            // {
            //     return view('admin/EditSurvey', [
            //         "validation" => $this->validator,"getQuestData" => $questionList
            //     ]);
            // } 
            //else 
            //{
            // $model = new TenantModel();
            // $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
            $userId = session()->get('id');
            $postData = $this->request->getPost();
            $data = [
                "campaign_name" => $this->escapeString($postData["campaign_name"]),
                "placeholder_name" => $this->escapeString($postData["placeholder_name"]),
                "question_id_1" => 1,
                "question_id_2" => 2,
                "answer_id_2" => implode(',', $postData["ans_2"]),
                "question_id_3" => 3,
                "answer_id_3" => implode(',', $postData["ans_3"]),
                "question_id_4" => 4,
                "answer_id_4" => implode(',', $postData["ans_4"]),
                "user_id" => $userId
            ];
            if ($tenantData['tenant_id'] == 1) {
                $model = new SurveyModel();
                $model->update($surv_id, $data);
            } else {
                $this->tenantUpdateSurvey($data, $tenantData, $surv_id);
            }
            session()->setFlashdata('response', "Survey updated successfully");
            $data = ["survey_Id" => 0];
            session()->set($data);
            return redirect()->to(base_url('survey/surveyList'));
        }

        return view('admin/EditSurvey', ["action" => "Edit", "surveyData" => $getSurveyData[0], "getQuestData" =>  $questionList, "answerList" => $answerList, "tenantData" => $tenantData, "optionsCount" => $optionsCountArray, "answerLimit" => $answerLimit]);
    }

    public function tenantUpdateSurvey($data, $tenantdata, $surv_id)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $cols = array();

        foreach ($data as $key => $val) {
            $cols[] = "$key = '$val'";
        }

        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_survey_details` SET " . implode(', ', $cols) . " WHERE `nps_survey_details`.`campaign_id` = " . $surv_id;
        $db->query($new_db_update_user);

        $model = new SurveyModel();
        $toEncFields = EncryptConstants::$survey;
        $this->modelHelper->UpdateData($model, $surv_id, $data, $toEncFields);
        $db->close();
    }
    public function GetSurveyList()
    {

        $data = [];
        $surveyList = null;
        $model = new TenantModel();
        $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
        array_push($data, $tenant);
        if ($tenant['tenant_id'] > 1) {
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $db->query('USE ' . $dbname);
        }
        $model = new SurveyModel();
        $condition=["status"=>"1"];
        $surveyList = $model->where($condition)->orderBy('created_at', 'DESC')->findAll();
        $this->DeleteDummySurveys();
        //$query = "SELECT * FROM `" . $dbname . "`.`nps_survey_details` WHERE `status`='1' ORDER BY `created_at` DESC";
        //$surveyList = $db->query($query)->getResultArray();
        $db?->close();
        $modelHelper = new ModelHelper();
        $toDecFields = EncryptConstants::$survey;
        $finalResult = $modelHelper->DecryptData($surveyList, $toDecFields);
        array_push($data, $finalResult);

        return $data;
    }
    public function surveyList()
    {
        $surveyListData = $this->GetSurveyList();
        $surveyList = $surveyListData[1];

        return view('admin/surveyList', ["surveyList" => $surveyList, "tenant" => $surveyListData[0]]);
    }
    public function DeleteDummySurveys()
    {

        $model = new SurveyModel();
        $condition = ['campaign_name' => ""];
        $model->where($condition)->delete();
    }
    public function getSurvey($survey_id)
    {

        $survey = null;
        $model = new TenantModel();
        $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
        if ($tenant['tenant_id'] == 1) {
            $model = new SurveyModel();
            $survey = $model->where('campaign_id', $survey_id)->find();
        } else {
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $query = "SELECT * FROM `" . $dbname . "`.`nps_survey_details` WHERE campaign_id='" . $survey_id . "'";
            $survey = $db->query($query)->getResultArray();
            $db->close();
        }
        $toDecFields =EncryptConstants::$survey;
        $survey = $this->modelHelper->DecryptData($survey, $toDecFields);
        return $survey;
    }
    public function deletesurvey($surv_id)
    {
        $modeldel = new TenantModel();
        $tenant = $modeldel->where('tenant_name', session()->get('tenant_name'))->first();
        $data = ['status' => '0'];
        if ($tenant['tenant_id'] == 1) {
            $model = new SurveyModel();
            $model->update($surv_id, $data);
        } else {
            $this->tenantDeleteSurvey($tenant, $surv_id);
        }
        session()->setFlashdata('response', "Survey deleted Successfully");
        return redirect()->to(base_url('survey/surveyList'));
    }
    public function tenantDeleteSurvey($tenantdata, $surv_id)
    {
        $data = ['status' => '0'];
        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $delete_query = "UPDATE  " . $dbname . ".nps_survey_details SET nps_survey_details.status = '" . $data['status'] . "' WHERE `nps_survey_details`.`campaign_id` = '" . $surv_id . "'";

        $db->query($delete_query);
        $db->close();
    }
    public function escapeString($val)
    {
        $db = db_connect();
        $connectionId = $db->connID;
        $val = mysqli_real_escape_string($connectionId, $val);
        return $val;
    }
}
