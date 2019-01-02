<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 31.08.18
 * Time: 09:23
 */

namespace the16thpythonist\Wordpress\Scopus;


use the16thpythonist\Wordpress\Scopus\WpScopus;
use the16thpythonist\Wordpress\Data\DataPost;
use Scopus\ScopusApi;
use Scopus\Response\Author;
use GuzzleHttp\Client;
use Exception;

/**
 * Class AuthorAffiliationFetcher
 *
 * CHANGELOG
 *
 * Added 31.08.2018
 *
 * @since 0.0.0.0
 *
 * @author Jonas Teufel<jonseb1998@gmail.com>
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorAffiliationFetcher
{
    public $author_id;
    public $scopus_api;

    public $filename;
    public $file;

    /**
     * AuthorAffiliationFetcher constructor.
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     */
    public function __construct()
    {
        /*
         * The API Key for the scopus database has to be set during the main register method of the package's facade
         * and can be fetched from the static field of that object.
         */
        $this->scopus_api = new ScopusApi(WpScopus::$API_KEY);
    }

    /**
     * Sets a new author id as the subject of the object. All further operations are based on the new author id
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $author_id
     */
    public function set(string $author_id) {
        $this->author_id = $author_id;

        $this->filename = $this->filename();
        /** @var DataPost file */
        $this->file = DataPost::create($this->filename);
    }

    /**
     * Updates the temporary file, which contains the affiliations for the author with the new blacklist and withelist
     * which is already within the AuthorPost object.
     *
     * CHANGELOG
     *
     * Added 02.01.2019
     *
     * @throws \BrowscapPHP\Exception\FileNotFoundException
     */
    public function updateAffiliations(){
        $author = AuthorPost::load($this->author_id);

        $affiliations = $this->file->load();
        $new_affiliations = array();
        foreach ($affiliations as $affiliation_id => $array) {
            $new_affiliations[$affiliation_id] = array(
                'name'      => $array['name'],
                'whitelist' => $author->isWhitelist($affiliation_id),
                'blacklist' => $author->isBlacklist($affiliation_id)
            );
        }
        $this->file->save($new_affiliations);
    }

    /**
     * Fetches all the affiliation info from scopus and returns a list with all names of affiliations the author had.
     *
     * The returned array is an associative array. The keys are the affiliation ids and the values are assoc. arrays
     * again, containing the following key value pairs:
     * name:        The string name of the affiliation
     * whitelist:   boolean value of whether or not that particular affiliation is whitelisted or not
     * blacklist:   boolean value of whether or not that particular affiliation is blacklisted or not
     *
     * Note: The whitelist and blacklist information will only be correct if the AuthorPost already exits. If this
     * is called during the creation of an AuthorPost or for an entirely nonexistent one, the info will not be correct.
     *
     * This function first gets all the affiliation ids from the author profile and then proceeds to send a new
     * request for every affiliation, since this might be a very long process, the list of all aff. names is being
     * saved into a temporary DataPost after each successful retrieval. This DataPost can be accessed from the
     * frontend to display the aff. step by step instead of having to wait for the entire list to finish
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @throws
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    public function fetchAffiliations() {

        $exists = AuthorPost::exists($this->author_id);
        if ($exists) {
            $author = AuthorPost::load($this->author_id);
        }

        $affiliations = array();

        $affiliation_ids = $this->fetchAffiliationIDs();
        var_export($affiliation_ids);

        foreach ($affiliation_ids as $affiliation_id) {
            try {
                $affiliation_name = $this->fetchAffiliationName($affiliation_id);
            } catch (\HttpException $e) {
                continue;
            }
            /*
             * If there already is an AuthorPost for this author ID, it will already contain the information of which
             * affiliation ID is black/whitelisted.
             * If there is no AuthorPost to the given author ID, whitelist and blacklist are being assumed as not set.
             */
            if ($exists) {
                $whitelist = $author->isWhitelist($affiliation_id);
                $blacklist = $author->isBlacklist($affiliation_id);
            } else {
                $whitelist = false;
                $blacklist = false;
            }
            $array = array(
                'name'      => $affiliation_name,
                'whitelist' => $whitelist,
                'blacklist' => $blacklist,
            );
            $affiliations[$affiliation_id] = $array;
            // Overrides the results file with the new array, which contains one result more
            $this->file->save($affiliations);
        }
        //var_export($affiliations);
        return $affiliations;
    }

    /**
     * Retrieves the author information from scopus and returns an array of all affiliation ids the author ever had
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    private function fetchAffiliationIDs() {
        try {
            $author = $this->scopus_api->retrieveAuthor($this->author_id);
            var_export($author);
            /*
             * The Scopus Api package saves the entire JSON array returned by the scopus website inside the protected
             * data field of each object, but only has a few getter methods defined for accessing this data.
             * By binding a closure that returns the field to the object, one can access the protected data anyways.
             */
            $closure = function () {
                return $this->data;
            };
            $data = \Closure::bind($closure, $author, Author::class)();
            var_export($data);
            /*
             * The affiliation history is an array which itself contains assoc arrays. These contain incomplete(!) data
             * about all the institutes the author has ever been affiliated with. The information sometimes(!)
             * includes the affilation id. This id can be used to make separate requests to the api to retrieve
             * the complete info.
             */
            $affiliation_history = $data['affiliation-history']['affiliation'];
            var_export($affiliation_history);
            $affiliations_ids = array();
            foreach ($affiliation_history as $affiliation) {
                try {
                    $affiliation_id = $affiliation['@id'];
                    $affiliations_ids[] = $affiliation_id;
                } catch (\Exception $e) {
                    continue;
                }
            }
            return $affiliations_ids;
        } catch (Exception $e) {
            echo $e->getMessage();
            return array();
        }
    }

    /**
     * Sends a request to the scopus api for a given affiliation ID and returns the name and city of the affiliation
     *
     * The string returned by this function will contain the name of the institute, as it is saved in the scopus
     * database and the name of the city in brackets after the name.
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @param string $affiliation_id
     * @return string
     * @throws \HttpException
     */
    private function fetchAffiliationName(string $affiliation_id) {
        /*
         * Sadly the Scopus API package by kasparsj doesnt include the possibility to directly request an affiliation.
         * This means the request has to be done manually using a HTTP Client (GuzzleHTTP).
         */
        $timeout = 30;
        $client = new Client([
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        /*
         * Making an affiliation request to the scopus api works by sending to the url beneath, but adding the concrete
         * id of the affiliation as the last part of the url.
         */
        $uri = 'https://api.elsevier.com/content/affiliation/affiliation_id/' . $affiliation_id;
        $options['query']['apiKey'] = WpScopus::$API_KEY;
        $response = $client->get($uri, $options);

        if ($response->getStatusCode() === 200) {
            $array = json_decode($response->getBody(), true);
            $content = $array['affiliation-retrieval-response'];
            $name = sprintf('%s (%s)', $content['affiliation-name'], $content['city']);
            return $name;
        } else {
            throw new \HttpException('The affiliation could not be retrieved');
        }
    }

    /**
     * Creates the filename of the temporary results data file from the currently set author ID
     *
     * CHANGELOG
     *
     * Added 31.08.2018
     *
     * @since 0.0.0.0
     *
     * @return string
     */
    public function filename() {
        $name = sprintf('affiliations_author_%s.json', $this->author_id);
        return $name;
    }
}