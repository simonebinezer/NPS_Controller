<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\Log\Logger;

use PHPMailer\PHPMailer\PHPMailer;
use Exception;
use CodeIgniter\Database\Exceptions\DatabaseException;
use App\Libraries\Response\Response;
use App\Libraries\Response\Error;
use App\Models\ModelFactory;
use App\Libraries\EnumsAndConstants\ModelNames;
use App\Libraries\EnumsAndConstants\Roles;
use App\Libraries\EnumsAndConstants\Status;
use App\Libraries\TokenManagement\TokenManagement;
use App\Libraries\Response\LoginData;
use CodeIgniter\HTTP\RequestInterface;

require_once APPPATH . 'Libraries/EnumsAndConstants/Enums.php';
class HomeController extends BaseController
{

    protected $user = null;
    protected $logger;

    public function __construct(RequestInterface $request)
    {


        $tokenManagement = new TokenManagement();
        $this->user = $tokenManagement->verify_token($request);
        // Initialize the Log library
        $this->logger = service('logger');
    }
}
