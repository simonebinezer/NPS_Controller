<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\TenantModel;
use App\Models\QuestionModel;
use App\Models\AnswerListModel;

class QandAController extends BaseController
{
    public $answercollection;

    public function __construct()
    {
        // load config file if not autoloaded
        $this->answercollection =  ["1" => 'Customer service', "2" => 'Free Shipping', "3" => 'Stock inventory', "4" => 'Order process', "5" => 'Quality', "6" => 'Work hours', "7" => 'in person visit', "8" => 'custom order', "9" => '24/7 support', "10" => 'Return policy'];
    }

    public function index()
    {
        return view('createquestion',  ["answercollection" => $this->answercollection]);
    }
    public function createQuestion()
    {
        $data = [];
        $model = new AnswerListModel();
        $answerdata = $model->where('created_id', session()->get('id'))->findall();
        $answerList = array();
        foreach ($answerdata as $key => $answerData) {
            array_push($answerList, $answerData['answer_name']);
        }

        if ($this->request->getMethod() == 'post') {
            $rules = [
                'question' => 'required|min_length[2]|max_length[100]',
                'qinfo' => 'required|min_length[2]|max_length[100]',
                'answer' => 'required',
            ];
            $errors = [
                'question' => [
                    'required' => 'You must choose a question.',
                ]
            ];

            if (!$this->validate($rules, $errors)) {

                return view('createquestion', [
                    "validation" => $this->validator,
                    "answercollection" => $answerList
                ]);
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
                $userId = session()->get('id');
                $questionId = $this->insertQuestion($this->request->getPost(), $userId);
                if ($tenant['tenant_id'] > 1) {
                    $this->tenantInsertQuestion($this->request->getPost(), $tenant, $questionId, $userId);
                }
                session()->setFlashdata('response', "Create new question Successfully");
                return redirect()->to(base_url('questionList'));
            }
        }
        return view('createquestion',  ["answercollection" => $answerList]);
    }
    public function insertQuestion($postData, $userId)
    {
        $model = new QuestionModel();
        $data = [
            "question_name" => $this->escapeString($postData["question"]),
            "description" => $this->escapeString($postData["qinfo"]),
            "info_details" => isset($postData["answer"]) ? $postData["answer"] : '',
            "other_option" => isset($postData["answerdata"]) ? json_encode($postData["answerdata"]) : '',
            "user_id" => $userId
        ];
        if (isset($postData["answer"]) && $postData["answer"] == 'other') {
            $data['priority'] =  isset($postData["priority"]) ? $postData["priority"] : '';
        }
        $result = $model->insertBatch([$data]);
        $db = db_connect();
        $questionId = $db->insertID();
        return $questionId;
    }

    public function updateQuestion($postData, $question_id)
    {
        $model = new QuestionModel();
        $data = [
            "question_name" => $this->escapeString($postData["question"]),
            "description" => $this->escapeString($postData["qinfo"]),
            "info_details" => isset($postData["answer"]) ? $postData["answer"] : '',
            "other_option" => isset($postData["answerdata"]) ? json_encode($postData["answerdata"]) : '',
        ];
        if (isset($postData["answer"]) && $postData["answer"] == 'other') {
            $data['priority'] =  isset($postData["priority"]) ? $postData["priority"] : '';
        }
        $model->update($question_id, $data);
    }
    public function tenantInsertQuestion($postData, $tenantdata, $question_id, $userId)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $data = [
            "question_id" => $question_id,
            "question_name" => $this->escapeString($postData["question"]),
            "description" => $this->escapeString($postData["qinfo"]),
            "info_details" => isset($postData["answer"]) ? $postData["answer"] : '',
            "other_option" => isset($postData["answerdata"]) ? json_encode($postData["answerdata"]) : '',
            "user_id" => $userId
        ];
        if (isset($postData["answer"]) && $postData["answer"] == 'other') {
            $data['priority'] =  isset($postData["priority"]) ? $postData["priority"] : '';
        }
        $key = array_keys($data);
        $values = array_values($data);
        $new_db_insert_user = "INSERT INTO " . $dbname . ".nps_question_details ( " . implode(',', $key) . ") VALUES('" . implode("','", $values) . "')";
        $db->query($new_db_insert_user);
    }
    public function escapeString($val)
    {
        $db = db_connect();
        $connectionId = $db->connID;
        $val = mysqli_real_escape_string($connectionId, $val);
        return $val;
    }
    public function tenantUpdateQuestion($postData, $tenantdata, $qid)
    {

        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $cols = array();
        $data = [
            "question_name" => $this->escapeString($postData["question"]),
            "description" => $this->escapeString($postData["qinfo"]),
            "info_details" => isset($postData["answer"]) ? $postData["answer"] : '',
            "other_option" => isset($postData["answerdata"]) ? json_encode($postData["answerdata"]) : '',
        ];
        if (isset($postData["answer"]) && $postData["answer"] == 'other') {
            $data['priority'] =  isset($postData["priority"]) ? $postData["priority"] : '';
        }
        foreach ($data as $key => $val) {
            $cols[] = "$key = '$val'";
        }

        $new_db_update_user = "UPDATE  " . $dbname . ".`nps_question_details` SET " . implode(', ', $cols) . " WHERE `nps_question_details`.`question_id` = " . $qid;
        $db->query($new_db_update_user);
    }
    public function questionList()
    {

        $questionList = null;
        $model = new TenantModel();
        $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
        if ($tenant['tenant_id'] == 1) {
            $model = new QuestionModel();
            $questionList = $model->find();
        } else {
            $dbname = "nps_" . $tenant['tenant_name'];
            //new DB creation for Tenant details
            $db = db_connect();
            $query = "SELECT * FROM `" . $dbname . "`.`nps_question_details`";
            $questionList = $db->query($query)->getResultArray();
            $db->close();
        }
        $model = new AnswerListModel();
        $answerdata = $model->where('created_id', session()->get('id'))->findall();
        $answerList = array();
        foreach ($answerdata as $key => $answerData) {
            array_push($answerList, $answerData['answer_name']);
        }
        return view('questionList', ["questionlist" => $questionList, "answercollection" => $answerList]);
    }
    public function editquestion($question_id)
    {
        $model = new QuestionModel();
        $getQuestData = $model->where('question_id', $question_id)->first();
        $model = new AnswerListModel();
        $answerdata = $model->where('created_id', session()->get('id'))->findall();
        $answerList = array();
        foreach ($answerdata as $key => $answerData) {
            array_push($answerList, $answerData['answer_name']);
        }
        if ($this->request->getMethod() == 'post') {
            $rules = [
                'question' => 'required|min_length[2]|max_length[100]',
                'qinfo' => 'required|min_length[2]|max_length[100]',
            ];
            $errors = [
                'question' => [
                    'required' => 'You must choose a question.',
                ]
            ];
            if (!$this->validate($rules, $errors)) {

                return view('editquestion', [
                    "validation" => $this->validator,
                    "answercollection" => $answerList
                ]);
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_name', session()->get('tenant_name'))->first();
                $userId = session()->get('id');
                $questionId = $this->updateQuestion($this->request->getPost(), $question_id);
                if ($tenant['tenant_id'] > 1) {
                    $this->tenantUpdateQuestion($this->request->getPost(), $tenant, $question_id);
                }
                session()->setFlashdata('response', "Update question Successfully");
                return redirect()->to(base_url('questionList'));
            }
        }
        return view('editquestion',  ["getQuestData" => $getQuestData, "answercollection" => $answerList]);
    }
    public function deletequestion($q_id)
    {
        $model = new QuestionModel();
        $getQuestData = $model->where('question_id', $q_id)->delete();
        $modeldel = new TenantModel();
        $tenant = $modeldel->where('tenant_name', session()->get('tenant_name'))->first();

        if ($tenant['tenant_id'] > 1) {
            $this->tenantDeleteQuestion($tenant, $q_id);
        }
        session()->setFlashdata('response', "question deleted Successfully");
        return redirect()->to(base_url('questionList'));
    }
    public function tenantDeleteQuestion($tenantdata, $q_id)
    {
        $dbname = "nps_" . $tenantdata['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $delete_query  = "DELETE FROM " . $dbname . ".`nps_question_details` WHERE `nps_question_details`.`question_id` =" . $q_id;
        $db->query($delete_query);
    }
    public function index1()
    {
        $data = $this->QuestionList1();
        $questionList = $data[1];
        //$tenant=$data[0];
        //return view('questionlist', ["questionlist" => $questionList, "tenant"=>$tenant]);
        return view('questionlist', ["questionlist" => $questionList]);
    }
    public function QuestionList1()
    {
        $data = [];
        $questionList = null;

        $model = new QuestionModel();
        $questionList = $model->find();

        return $questionList;
    }
}
