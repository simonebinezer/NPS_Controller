<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Models\TenantModel;
use App\Models\QuestionModel;
use App\Models\SurveyModel;
use App\Models\AnswerListModel;

class Sitemap extends BaseController
{
    // public function index()
    // {
    //     $data = $this->AnswerList();
    //     $answerList = $data[1];
    //     $tenant = $data[0];
    //     return view('admin/answerlist', ["getAnswerData" => $answerList, "tenant" => $tenant]);
	// }
	public function __construct()
    {
        helper('url'); // Load the URL helper if you need it
    }
    public function siteUrl()
    {

        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Define URLs dynamically or statically
        $urls = [
            ['loc' => base_url(), 'lastmod' => '2023-10-01', 'changefreq' => 'daily', 'priority' => '1.0'],
	    ['loc' => base_url('login'), 'lastmod' => '2023-09-30', 'changefreq' => 'weekly', 'priority' => '0.8'],
	    ['loc' => base_url('signup'), 'lastmod' => '2023-09-30', 'changefreq' => 'weekly', 'priority' => '0.8'],
	    ['loc' => base_url('forgot'), 'lastmod' => '2023-09-30', 'changefreq' => 'weekly', 'priority' => '0.8'],
	    ['loc' => base_url('how-it-works'), 'lastmod' => '2024-10-22', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['loc' => base_url('improve-NPS-response-rate'), 'lastmod' => '2024-10-22', 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['loc' => base_url('images/CXAnalytix-complete-guide.pdf'), 'lastmod' => '2024-10-22', 'changefreq' => 'weekly', 'priority' => '0.8']

            // Add more URLs here or fetch from the database
        ];

        foreach ($urls as $url) {
            $sitemap .= '<url>';
            $sitemap .= '<loc>' . $url['loc'] . '</loc>';
            $sitemap .= '<lastmod>' . $url['lastmod'] . '</lastmod>';
            $sitemap .= '<changefreq>' . $url['changefreq'] . '</changefreq>';
            $sitemap .= '<priority>' . $url['priority'] . '</priority>';
            $sitemap .= '</url>';
        }

        $sitemap .= '</urlset>';

        header("Content-Type: application/xml; charset=UTF-8");
        echo $sitemap;
    }
}


