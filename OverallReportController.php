<?php

namespace App\Controllers;

use App\Controllers\BaseController;

use App\Models\UserModel;
use App\Models\TenantModel;
use App\Models\QuestionModel;
use App\Models\SurveyModel;
use App\Models\ExternalcontactsModel;
use App\Models\CreatecontactsModel;
use App\Models\SurveyResponseModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;


class OverallReportController extends BaseController
{
    public function index()
    {
        //
        $selectTenant = $this->request->getGet("tenantId") ? $this->request->getGet("tenantId"): '';
        $model = new TenantModel();  
        $getTenantdata = $model->findall(); 
        $data = [            
            "getTenantdata" => $getTenantdata,
            "selectTenant" => $selectTenant,
        ];
        return view('admin/reportresult', ["getdashData" => $data]);
    }
    public function comingSoon()
    {
        //
       
        return view('coming-soon');
    }
    public function comingSoongeo()
    {
        //
       
        return view('geo');
    }
    public function contactus()
    {
        //
       
        return view('contactus');
    }

    public function excelGenerateCurrentYr($data){
        return view('template/excel_view', ["overallreport" => $data]);
    }
    public function pdfGenerateCurrentYr($status, $checkyr){
        $userId = array();

        $tenantId = ($this->request->getGet("tenantId") != '') ? $this->request->getGet("tenantId") :  session()->get('tenant_id');

        $model = new TenantModel();
        $tenant = $model->where('tenant_id', $tenantId)->first();
        if ($tenantId > 1) {
           
            $dbname = "nps_" . $tenant['tenant_name'];
            $db = db_connect();
            $db->query('USE ' . $dbname);
        }

        // if($this->request->getGet("tenantId") == '1' || $this->request->getGet("tenantId") == '' ) {
        //     $model = new UserModel();
        //     $userlist = $model->where('tenant_id', session()->get('tenant_id'))->findall();
        // }else {
        //     $model = new UserModel();
        //     $userlist = $model->where('tenant_id', $this->request->getGet("tenantId"))->findall();
        //     $selectTenant = $this->request->getGet("tenantId");
        // }
        $model = new UserModel();
        $userlist = $model->where('tenant_id', $tenantId)->findall();
        foreach($userlist as $userarray){
            array_push($userId, $userarray['id']);
        }
        // nps_survey_response Table for getting response data
        $model = new SurveyResponseModel();
      
         $model->whereIn('user_id', $userId);
        if($status == 'no'){
            $model->orderBy('created_at' , "DESC");
            if($checkyr == 'yes'){
                $filename = "NPS_Report_2020_".date("Y").".pdf";
                // $model->where("CAST(created_at AS DATE) BETWEEN 2020 AND date('Y')");
            } else {
                $filename = "NPS_Report_".date("Y").".pdf";
            }
        } else if($status == 'high'){
            $model->orderBy("CAST(answer_id AS INT)" , "DESC");
            $filename = "NPS_Report_High".date("Y").".pdf";
        }  else if($status == 'low'){
            $model->orderBy("CAST(answer_id AS INT)" , "ASC");
            $filename = "NPS_Report_Low".date("Y").".pdf";
        }
        $getSurveyData = $model->find();
        $db = db_connect();
        $query = $db->getLastQuery();
//         echo (string)$query; 
//  exit;
        $getfullcollection = array();
        foreach($getSurveyData as $key => $getdata){
            $model = new SurveyModel(); 
            $surveryList = $model->where('campaign_id', $getdata['campaign_id'])->first();
            $model = new UserModel();
            $userData = $model->where('id', $getdata['user_id'])->first();   
            $model = new QuestionModel(); 
            $answer_id = $getdata['answer_id'];
            $getquestionData = $model->where('question_id', $getdata['question_id'])->first(); 
            if($answer_id >8){
                $priority = 3;
            }else if($answer_id < 9 && $answer_id >6){
                $priority = 2;
            } else {
                $priority = 1;
            }
            $getquestionData2 = $model->whereIn('user_id', $userId)->where('priority', $priority)->first(); 

            $model = new ExternalcontactsModel();  
            $condition=array('email_id'=> $getdata['email'], 'status'=> 1);
            $getcontactData = $model->where($condition)->first(); 
            $questionData = array();
            array_push($questionData, $getquestionData, $getquestionData2);
            $getSurveycollection = [
                "surveyresponseData" => $getdata,
                "surveyData" => $surveryList,
                "questiondata1" => $getquestionData,
                "questiondata2" => $getquestionData2,
                "contactData" => $getcontactData,
                "userData" => $userData,
                "tenantData" => $tenant
            ];  
            array_push($getfullcollection, $getSurveycollection);
 
        }
        $dompdf = new Dompdf(); 
        $dompdf->loadHtml(view('template\pdf_view', ["fulldata" => $getfullcollection]));
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->set_option('isHtml5ParserEnabled', true);
        $dompdf->render();
        $dompdf->stream($filename);
    }
}
