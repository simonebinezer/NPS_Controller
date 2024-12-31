<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ModelHelper;
use App\Models\UserModel;
use App\Models\SurveyResponseModel;
use App\Models\ExternalcontactsModel;
use App\Models\CreatecontactsModel;
use App\Models\SurveyModel;
use App\Models\TenantModel;
use App\Libraries\EnumsAndConstants\EncryptConstants;

require_once APPPATH."Libraries/EnumsAndConstants/Constants.php";

class AdminController extends BaseController
{
    private $modelHelper = null;
    public function __construct()
    {
        if (session()->get('role') != "admin") {
            echo 'Access denied';
            exit;
        }
        if (session()->get("tenant_id") > 1) {
            $this->modelHelper = new ModelHelper();
        }
    }
    public function index()
    {
        if (session()->get('tenant_id') == 1) {
            return redirect()->to(base_url('tenant/createtenant'));
        }
        $getRequest = $this->request->getGet();
        $tenantId = ($this->request->getGet("tenantId") != '') ? $this->request->getGet("tenantId") :  session()->get('tenant_id');
        $surveyId = isset($getRequest["surveyId"]) ? $getRequest["surveyId"] : 0;


        //get tenant list
        $model = new TenantModel();
        $getTenantdata = $model->findall();

        // Filter concept
        $selectTenant = '';
        $selectRange = '';
        $selectfilter = '';
        $detractorsArray1  = array();
        $passivesArray1   = array();
        $promotersArray1  = array();
        $getResponseCount  = array();

        //get users list based on tenant
        if ($this->request->getGet("tenantId") == '1' || $this->request->getGet("tenantId") == '') {
            $model = new UserModel();
            $userlist = $model->where('tenant_id', session()->get('tenant_id'))->findall();
        } else {
            $model = new UserModel();
            $userlist = $model->where('tenant_id', $this->request->getGet("tenantId"))->findall();
            $selectTenant = $this->request->getGet("tenantId");
        }
        $userId = array();
        foreach ($userlist as $userarray) {
            array_push($userId, $userarray['id']);
        }

        $daterange = '';
        $comparestartdate = '';
        $compareenddate = '';
        $datacompare = array();
        $startDate = date('Y-m-d H:i:s', strtotime(' - 32 days'));
        $endDate  = date("Y-m-d H:i:s");
        if ($this->request->getGet("daterange") != '') {
            $daterange = explode("_", $this->request->getGet("daterange"));
            $startDate  =   date("Y-m-d H:i:s", strtotime($daterange[0]));
            $endDate    =   date("Y-m-d H:i:s", strtotime($daterange[1]));
            $returnDate1 = date("d-F-Y", strtotime($daterange[0]));
            $returnDate2 = date("d-F-Y", strtotime($daterange[1]));
            $returnDate = $returnDate1 . "_" . $returnDate2;
            $selectRange = $returnDate;
            $daysdiffer = intval(abs((strtotime($daterange[0]) - strtotime($daterange[1])) / 86400));
            $comparestartdate = date("Y-m-d H:i:s", strtotime($daterange[0] . "-" . $daysdiffer . " days "));
            $compareenddate = date("Y-m-d H:i:s", strtotime($daterange[1] . "-" . $daysdiffer . " days -1 second"));
        }

        //Commented the compare functionality as asked by Prabhu on 27th oct 2023
        // if ($this->request->getGet("filter")  == "yes") {
        //     $c_promotersArray1 =  array();
        //     $c_passivesArray1 =  array();
        //     $c_detractorsArray1 = array();
        //     $selectfilter = $this->request->getGet("filter");
        //     $model = new SurveyResponseModel();
        //     if (is_array($daterange)) {
        //         $model->where("CAST(created_at AS DATE) BETWEEN '$comparestartdate' AND '$compareenddate'");
        //     }
        //     $getSurveyDatacompare = $model->whereIn('user_id', $userId)->findall();
        //     foreach ($getSurveyDatacompare as $key => $getSurveylist) {
        //         if ($getSurveylist['answer_id'] > 8) {
        //             array_push($c_promotersArray1, $getSurveylist['answer_id']);
        //         }
        //         if ($getSurveylist['answer_id'] <= 8 && $getSurveylist['answer_id'] > 6) {
        //             array_push($c_passivesArray1, $getSurveylist['answer_id']);
        //         }
        //         if ($getSurveylist['answer_id'] <= 6) {
        //             array_push($c_detractorsArray1, $getSurveylist['answer_id']);
        //         }
        //     }
        //     $comp_completedData = array_merge($c_promotersArray1, $c_passivesArray1, $c_detractorsArray1);
        //     $getCompareValue = $this->getcomparesurvey($tenantId, $userId,  $this->request->getGet("tenantId"), $comparestartdate, $compareenddate, $comp_completedData);
        //     $datacompare = [
        //         "promoters" => $c_promotersArray1,
        //         "passives" => $c_passivesArray1,
        //         "detractors" =>  $c_detractorsArray1,
        //         "getsurveyresponse" => $getCompareValue['getsurveyresponse'],
        //         "totalresponse" => $getCompareValue['totalresponse'],
        //         "getfullResponse" => $getCompareValue['getfullResponse'],
        //         "revenueData" => $getCompareValue['revenueData']
        //     ];
        // }


        $emailSendList = array();
        $surveyResponseList = array();
        $surveyList = array();
        $lastSurvey = null;
        if ($tenantId == 1) {

            //get all survey details
            $model = new SurveyModel();
            //$model->where("CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'");
            $surveyEncList = $model->whereIn('user_id', $userId)->where('sent_status', '0')->where('status', '1')->findall();
            $toDecFields = EncryptConstants::$survey;
            $surveyList = $this->modelHelper->DecryptData($surveyEncList, $toDecFields);
            if ($surveyId == 0) {
                $lastSurvey = end($surveyList);
                $surveyId = $lastSurvey ? $lastSurvey['campaign_id'] : 0;
            }
            //get all the survey response data
            $model = new SurveyResponseModel();
            $model->where("CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'");
            $surveyResponseList = $model->where('campaign_id', $surveyId)->whereIn('user_id', $userId)->findall();

            //get email send list data
            $model = new CreatecontactsModel();
            $model->where("CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'");
            $emailSendList = $model->where('survey_id', $surveyId)->whereIn('user_id', $userId)->findall();


            //get external contacts
            //currently not needed 
            // $model = new ExternalcontactsModel();
            // $model->where("CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'");
            // $customerList = $model->whereIn('created_by', $userId)->findall();

        } else {
            $model = new TenantModel();
            $tenant = $model->where('tenant_id', $tenantId)->first();
            $dbname = "nps_" . $tenant['tenant_name'];

            //new DB creation for Tenant details
            $db = db_connect();
            $db->query('USE ' . $dbname);
            $userIdlist = implode(",", $userId);

            //get all survey details data
            $multiClause = "SELECT * FROM " . $dbname . ".nps_survey_details WHERE `nps_survey_details`.`user_id` IN (" . $userIdlist . ") AND `nps_survey_details`.`sent_status`= '0' AND `nps_survey_details`.`status`= '1'";
            //$multiClause .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
            $surveyListResult = $db->query($multiClause);
            if (count($surveyListResult->getResultArray()) > 0) {
                $surveyEncList = $surveyListResult->getResultArray();
                $toDecFields = EncryptConstants::$survey;
                $surveyList = $this->modelHelper->DecryptData($surveyEncList, $toDecFields);
            }

            if ($surveyId == 0) {
                $lastSurvey = end($surveyList);
                $surveyId = $lastSurvey ? $lastSurvey['campaign_id'] : 0;
            }

            //get all the survey response data
            $multiClause = "SELECT * FROM " . $dbname . ".nps_survey_response  WHERE `nps_survey_response`.`user_id` IN (" . $userIdlist . ") AND `nps_survey_response`.`campaign_id`=" . $surveyId;
            $multiClause .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
            $externalcount = $db->query($multiClause);
            if (count($externalcount->getResultArray()) > 0) {
                $surveyResponseList = $externalcount->getResultArray();
            }

            //get email send list data
            $multiClause = "SELECT * FROM " . $dbname . ".nps_email_send_list  WHERE `nps_email_send_list`.`user_id` IN (" . $userIdlist . ") AND `nps_email_send_list`.`survey_id`=" . $surveyId;
            $multiClause .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
            $externalcount = $db->query($multiClause);
            if (count($externalcount->getResultArray()) > 0) {
                $emailSendList = $externalcount->getResultArray();
            }

            //get external contacts
            //currently not needed 
            // $multiClause = "SELECT * FROM " . $dbname . ".nps_external_contacts  WHERE `nps_external_contacts`.`created_by` IN (" . $userIdlist . ")";
            // $multiClause .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
            // $externalcount = $db->query($multiClause);
            // if (count($externalcount->getResultArray()) > 0) {
            //     $customerList = $externalcount->getResultArray();
            // }
            $db->close();
        }
        foreach ($surveyResponseList as $key => $surveyResponse) {
            if ($surveyResponse['answer_id'] > 8) {
                array_push($promotersArray1, $surveyResponse['answer_id']);
            }
            if ($surveyResponse['answer_id'] <= 8 && $surveyResponse['answer_id'] > 6) {
                array_push($passivesArray1, $surveyResponse['answer_id']);
            }
            if ($surveyResponse['answer_id'] <= 6) {
                array_push($detractorsArray1, $surveyResponse['answer_id']);
            }
        }

        //get unique email send list
        $responseOverall = array();
        foreach ($emailSendList as $key => $overalldata) {
            $split_email = explode(",", $overalldata['email_list']);
            foreach ($split_email as $splitEmail) {
                array_push($responseOverall, $splitEmail);
            }
        }
        $responseOverall = array_unique($responseOverall);

        $promoters = $promotersArray1;
        $passives = $passivesArray1;
        $detractors = $detractorsArray1;
        $completedData = array_merge($promoters, $passives, $detractors);

        $uniqueEmailSendListCount = (is_array($responseOverall)) ? count($responseOverall) : '';
        $surveyResponseListCount = (is_array($surveyResponseList)) ? count($surveyResponseList) : '';
        $aggregatedNPS = array_count_values($completedData);
        $revenueData = count($completedData);
        $datacompare["totalresponse"] =  $uniqueEmailSendListCount;
        $data = [
            "promoters" => $promoters,
            "passives" => $passives,
            "detractors" =>  $detractors,
            "getsurveyresponse" => $surveyResponseListCount,
            "totalresponse" =>  $uniqueEmailSendListCount,
            "getfullResponse" => $aggregatedNPS,
            "revenueData" => $revenueData,
            "getTenantdata" => $getTenantdata,
            "surveyList" => $surveyList,
            "selectTenant" => $selectTenant,
            "selectfilter" => $selectfilter,
            "selectRange" => $selectRange,
            "selectedSurvey" => $surveyId,
        ];
        return view("admin/dashboard", ["getdashData" => $data, "getDatacomp" => $datacompare, "subscription" => session()->get("subscriptionFlag")]);
    }
    public function getcomparesurvey($tenantId, $userId, $getTenantId, $comp_start, $comp_end, $completedData)
    {
        $returnDate = $comp_start . "_" . $comp_end;
        if ($tenantId == 1) {
            if ($getTenantId == '1' || $getTenantId == '') {
                $model = new ExternalcontactsModel();
                $model->where("CAST(created_at AS DATE) BETWEEN '$comp_start' AND '$comp_end'");
                $getcontactdata = $model->whereIn('created_by', $userId)->findall();
                $model = new SurveyResponseModel();
                $model->where("CAST(created_at AS DATE) BETWEEN '$comp_start' AND '$comp_end'");
                $getsurveylist = $model->whereIn('user_id', $userId)->findall();
            } else {
                $model = new TenantModel();
                $tenant = $model->where('tenant_id', $getTenantId)->first();
                $gettotal = $this->getTotalResponse($tenant, $userId, $comp_start, $comp_end);
                $getcontactdata = $gettotal['getcontactdata'];
                $getsurveylist = $gettotal['getsurveylist'];
            }
        } else {
            $model = new TenantModel();
            $tenant = $model->where('tenant_id', $tenantId)->first();
            $gettotal = $this->getTotalResponse($tenant, $userId, $comp_start, $comp_end);
            $getcontactdata = $gettotal['getcontactdata'];
            $getsurveylist = $gettotal['getsurveylist'];
        }
        $totalresponse = (is_array($getcontactdata)) ? count($getcontactdata) : '';
        $getsurveyresponse = (is_array($getsurveylist)) ? count($getsurveylist) : '';
        $getfullResponse = array_count_values($completedData);
        $revenueData = count($completedData);
        $dataComp = [
            "getsurveyresponse" => $getsurveyresponse,
            "totalresponse" => $totalresponse,
            "getfullResponse" => $getfullResponse,
            "revenueData" => $revenueData
        ];
        return $dataComp;
    }
    public function getTotalResponse($tenant, $userId, $startDate, $endDate)
    {

        $dbname = "nps_" . $tenant['tenant_name'];
        //new DB creation for Tenant details
        $db = db_connect();
        $db->query('USE ' . $dbname);
        $getcontactdata = '';
        $getsurveylist = array();
        $userIdlist = implode(",", $userId);
        $multiClause1 = "SELECT * FROM " . $dbname . ".nps_external_contacts  WHERE `nps_external_contacts`.`created_by` IN (" . $userIdlist . ")";
        $multiClause1 .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
        $externalcount = $db->query($multiClause1);
        if (count($externalcount->getResultArray()) > 0) {
            $getcontactdata = $externalcount->getResultArray();
        }
        $multiClause2 = "SELECT * FROM " . $dbname . ".nps_survey_response  WHERE `nps_survey_response`.`user_id` IN (" . $userIdlist . ")";
        $multiClause2 .= " AND CAST(created_at AS DATE) BETWEEN '$startDate' AND '$endDate'";
        $externalcount = $db->query($multiClause2);
        if (count($externalcount->getResultArray()) > 0) {
            $getsurveylist = $externalcount->getResultArray();
        }

        $db->close();
        $data = [
            "getcontactdata" => $getcontactdata,
            "getsurveylist" => $getsurveylist
        ];
        return $data;
    }
    public function ajaxrequest()
    {
        echo view('ajax-request');
    }

    public function updateRole()
    {
        $output = "Ajax request Success.";
        if ($this->request->isAJAX()) {
            $query = service('request')->getPost();
            $userId = $query['id'];
            $data = [
                "role" => $query['query']
            ];
            $model = new UserModel();
            $model->update($userId, $data);
            var_dump($this->request->getPost('query'));
            echo json_encode(['success' => $output, 'csrf' => csrf_hash(), 'query ' => $query]);
        }
        // echo json_encode($output);
    }
}
