<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 31.08.18
 * Time: 09:23
 */

namespace the16thpythonist\Wordpress\Scopus;


use the16thpythonist\Wordpress\Scopus\WpScopus;
use Scopus\ScopusApi;


class AuthorAffiliationFetcher
{
    public $author_id;
    public $scopus_api;

    public function __construct(string $author_id)
    {
        $this->author_id;
        /*
         * The API Key for the scopus database has to be set during the main register method of the package's facade
         * and can be fetched from the static field of that object.
         */
        $this->scopus_api = new ScopusApi(WpScopus::$API_KEY);
    }


}