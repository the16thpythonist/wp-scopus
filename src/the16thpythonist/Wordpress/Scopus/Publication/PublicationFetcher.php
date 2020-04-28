<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 12:41
 */

namespace the16thpythonist\Wordpress\Scopus\Publication;

use Scopus\ScopusApi;
use Closure;
use Scopus\Response\Abstracts;
use Scopus\Response\AbstractCoredata;

// 28.04.2020 After namespace change
use the16thpythonist\Wordpress\Scopus\Author\AuthorObservatory;
use the16thpythonist\Wordpress\Scopus\WpScopus;

/**
 * Class PublicationFetcher
 *
 * CHANGELOG
 *
 * Added 28.10.2018
 *
 * @since 0.0.0.2
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class PublicationFetcher
{

    public $args;

    // The amount of publications, that have to be fetched
    public $fetch_count;

    /**
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * @var int     The current amount of publications, that have been fetched
     */
    public $current_count;

    // The complete list of all the ids to be fetched
    public $fetch_ids;

    public $author_ids;

    // The author observatory offers functionality for all the observed authors
    public $author_observatory;

    // The API wrapper object, which will handle the actual network communication with the scopus database
    public $api;

    // The Abstract object returned for the last iteration
    /**
     * @var Abstracts $abstract
     */
    public $abstract;

    // The title of the last iterations publication
    public $title;

    // The scopus id, that was requested in the last iteration
    public $scopus_id;

    // The post id, of the post, that was created in the last iteration
    public $post_id;

    public $publication_cache;

    public $log;

    /**
     * This is the array, which contains the default arguments passed to the constructor of a new object in case there
     * are none specified.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * Changed 31.12.2018
     * Added the 'count' argument. It specifies how many publications are supposed to be fetched. It is an integer
     * value. In case it is a negative value that means that ALL publications possible are supposed to be fetched.
     */
    const DEFAULT_ARGS = array(
        'date_limit'            => '2016-01-01',
        'collaboration_limit'   => 50,
        'author_limit'          => 10,
        'count'                 => -1
    );

    /**
     * PublicationFetcher constructor.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * Changed 30.10.2018
     * Now using an additional object, the PublicationMetaCache, which will save the publishing date for every fetched
     * publication and make it possible to check if it is too old even before the database request has to be made.
     *
     * @since 0.0.0.2
     *
     * @param array $args
     * @param $log
     */
    public function __construct(array $args, $log)
    {
        $this->args = array_replace(self::DEFAULT_ARGS, $args);
        $this->log = $log;

        // Creating a new author observatory object
        $this->author_observatory = new AuthorObservatory();
        $this->author_observatory->log = $this->log;
        $this->log->info('CREATED A NEW AUTHOR OBSERVATORY');

        // We need the scopus author ids of all the observed authors to match them against the author ids of each
        // individual publication
        $this->author_ids = $this->author_observatory->getAuthorIDs();
        $this->log->info(sprintf('TOTAL AMOUNT OF OBSERVED AUTHORS "%s"', count($this->author_ids)));
        $this->log->info(sprintf('AUTHORS "%s"', implode(', ', $this->author_ids)));

        // Creating a new api object. This will do the actual communication with the scopus database
        $this->api = new ScopusApi(WpScopus::$API_KEY);
        $this->log->info('CREATED A NEW SCOPUS API OBJECT');

        // 30.10.2018
        // The publication cache object will contain meta data about all publications of all authors. The meta data
        // includes the publishing date, which will make it possible to not have to request a publication from the
        // scopus data base to know it is too old
        $this->publication_cache = new PublicationMetaCache();
        $this->log->info(sprintf("CREATED A NEW PUBLICATION CACHE WITH '%s' entries", count($this->publication_cache->data)));

        // First we ask the author observatory, to get us all the scopus ids of all the publications of all the authors.
        // But obviously we only want to get the new publications, that is why we also get the scopus ids of all the
        // publications already on the website and the array difference between the two makes all the publications
        // we still have to fetch from the data base
        $all_scopus_ids = $this->author_observatory->fetchScopusIDs();
        $this->log->info(sprintf('ALL PUBLICATIONS OF ALL AUTHORS: "%s"', count($all_scopus_ids)));

        $publication_posts = PublicationPost::getAll(TRUE, TRUE);
        $this->log->info(sprintf('ALL PUBLICATIONS ON THE WEBSITE: "%s"', count($publication_posts)));
        $old_scopus_ids = array_map(function ($publication_post) {return $publication_post->scopus_id; }, $publication_posts);

        $this->fetch_ids = array_unique(array_diff($all_scopus_ids, $old_scopus_ids));
        // We additionally shuffle the elements of the array, to not get into a situation where in order there are
        // a lot of errors and at the beginning of each fetch process there just are like 100 errors time and time again
        shuffle($this->fetch_ids);
        $this->fetch_count = count($this->fetch_ids);
        $this->log->info(sprintf('REMAINING PUBLICATIONS TO FETCH: "%s"', $this->fetch_count));
    }

    /**
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * Changed 30.10.2018
     * Before requesting the publication data from the scopus data base the publication meta data cache gets checked
     * if it contains the meta data for the cutrrent scopus id and if that is the case, the date gets checked against
     * the limit, before the actual data is requested and causing network traffic.
     * Thus the cache is being updated with the publishing date for every publication after it was fetched.
     *
     * Changed 31.10.2018
     * Adding the publication title as info to the publication meta cache and also loading it to display in the log
     * message.
     *
     * Changed 06.11.2018
     * If a publication is from an unknown collaboration, it will get posted as a draft and needs to be revisited to
     * determine, which collaboration it is.
     *
     * Changed 31.12.2018
     * The current_count attribute now saves how many publications have already been inserted into the system and if
     * given a count value during the creation of the fetcher object, the loop will exit after that many publications
     * were inserted.
     *
     * Changed 28.11.2019
     * Moved the whole process of checking whether a cached publication is too old into its own function
     * "checkCacheTooOld"
     * Calling a new function "checkCacheExcluded", which will check the publication meta cache, for the boolean exclude
     * flag being set, which can be used to exclude a publication from being fetched from scopus ever again.
     *
     * Changed 03.12.2019
     * Added the additional entry "topics" to the args array, which is used to insert new publications. This is a string
     * which is essentially the concatenation of the categories array. This string will be used to sort
     * the admin list view of the publication posts by the used topics.
     *
     * Changed 27.01.2020
     * The collaboration value is now being determined by the new private function "getCollaboration", which will also
     * check the publication cache for a saved value first...
     *
     * @since 0.0.0.2
     *
     * @return \Generator
     */
    public function next() {

        $this->log->info('STARTING TO FETCH PUBLICATIONS');
        foreach ($this->fetch_ids as $scopus_id) {

            // 31.12.2018
            // In case the given count value is negative (usually -1) all publications will be inserted. Otherwise if
            // the current count exceeds the count value the loop will terminate
            if ( !($this->args['count'] < 0) && $this->args['count'] == $this->current_count ) {
                $this->log->info(sprintf('Loop terminated after fetching "%s" pubs.', $this->current_count));
                break;
            }

            // 30.10.2018
            // Before we request the publication from the scopus database, we check if the publication cache contains
            // an entry about the current scopus id and if it does we check the publishing date from the cache with
            // the date limit and eventually dismiss the publication before causing unnecessary network traffic

            // 31.10.2018
            // Getting the title of the publication from the cache now, because before it would just use the title
            // of the last published publication for all log entries after that
            if ($this->publication_cache->contains($scopus_id)) {
                // First we check if the publication is too old
                if ($this->checkCacheTooOld($scopus_id)) {
                    continue;
                }

                // 28.11.2019
                // Now we also need to check for the "exclude" boolean flag within the meta cache. If it is true the
                // publication should also not be loaded...
                if ($this->checkCacheExclude($scopus_id)) {
                    continue;
                }
            }

            try {
                $this->abstract = $this->api->retrieveAbstract($scopus_id);
            } catch(\Exception $e) {
                $this->log->error(sprintf('ERROR IN "%s"', $scopus_id));
                continue;
            }

            $coredata = $this->abstract->getCoredata();
            $this->title = $coredata->getTitle();

            // 30.10.2018
            // After it was fetched, we will update the information about the publication in the cache, so that
            // during the next fetch process, we can use the cached meta data.
            // At this point only the publishing date is saved inside as meta data in the cache.

            // 31.10.2018
            // Also adding the title of the publication as meta data to the cache
            $this->publication_cache->write(
                $scopus_id,
                $coredata->getTitle(),
                $coredata->getCoverDate()
            );

            // Checking if the publication is too old, by comparing it with the max date given by the arguments
            $date = $coredata->getCoverDate();
            $time_difference = strtotime($date) - strtotime($this->args['date_limit']);
            if ($time_difference <= 0) {
                $this->log->info(sprintf('DISMISSED, TOO OLD: "%s" PUBLISHED "%s"', $this->title, $date));
                continue;
            }

            // Checking for the author black and white listings
            $publication_check = $this->author_observatory->checkPublication($this->abstract);

            // 31.12.2018
            // Here we extract the information of the affiliation ids for the observed authors of the publication to
            // also save them within the PublicationPost object
            $author_affiliations = $this->author_observatory->getAffiliationsPublication($this->abstract, TRUE);

            // 06.11.2018
            // Chose a stricter affiliation policy: Everything, that is not exactly whitelisted gets dismissed right
            // away.
            if ($publication_check <= 0) {
                $this->log->info(sprintf('DISMISSED, BLACKLISTED: "%s"', $this->title));
                continue;
            }

            // Will return an array containing the authors
            $authors = $this->getAuthors();
            $author_count = count($authors);

            // 27.01.2020
            // Returns the string to be used as the collaboration value of the publication
            $collaboration = $this->getCollaboration();

            // 06.11.2018
            // Added a 'status' option to the argument array, which can be 'publish' or 'draft'. If there is a
            // collaboration publication, we don't want that on the site until we know which publication it is from
            // and that has to be evaluated by another script or manually at the moment.
            // 31.12.2018
            // Added the 'author_affiliations' argument to also be inserted.
            // 03.12.2019
            // Moved the computation of the categories array outside of the array definition.
            // Added a new field to the args array "topics". This field will contain a string, which is the
            // concatenation of the categories array. This string is being saved as a meta key and will help to order
            // the admin list view by the topics
            $categories = array_merge($this->author_observatory->getCategoriesPublication($this->abstract), array('Publications'));
            $topics = array_intersect($categories, ScopusOptions::getAuthorCategories());
            sort($topics);
            $args = array(
                'title'                 => $this->title,
                'status'                => ($collaboration == 'ANY' ? 'draft' : 'publish'),
                'abstract'              => $coredata->getDescription(),
                'published'             => $coredata->getCoverDate(),
                'scopus_id'             => $coredata->getScopusId(),
                'doi'                   => $coredata->getDoi(),
                'eid'                   => $this->getAbtractEid($this->abstract),
                'issn'                  => $coredata->getIssn(),
                'journal'               => $coredata->getPublicationName(),
                'volume'                => $coredata->getVolume(),
                'tags'                  => $this->getTags($this->abstract),
                'authors'               => $authors,
                'author_count'          => $author_count,
                'author_affiliations'   => $author_affiliations,
                'categories'            => $categories,
                'collaboration'         => $collaboration,
                'topics'                => implode(', ', $topics),
            );
            try{
                $this->post_id = PublicationPost::insert($args);
            } catch (\Error $e) {
                $this->log->error($e->getMessage());
            }

            $this->log->info(sprintf('<a href="%s">PUBLICATION "%s"</a>',get_the_permalink($this->post_id), $this->title));

            // 31.12.2018
            // Here we are incrementing the current amount of fetched publications, only after we have successfully
            // inserted the new publication into the system
            $this->current_count += 1;

            yield $this->post_id;
        }

        // 30.10.2018
        // Saving the publication meta cache persistently
        $this->publication_cache->save();
    }

    /**
     * Returns an array, where the keys are the author names and the values are the author ids of the authors of the
     * current publication. The list is limited to the amount passed by the arguments.
     *
     * CHANGELOG
     *
     * Added 29.10.2018
     *
     * @since 0.0.0.2
     *
     * @return array
     */
    public function getAuthors() {
        $result = array();
        $authors = $this->abstract->getAuthors();

        // For the generation of the author metrics it is important, that if there are any observed authors also
        // authors of the publication, that these will preferably added as author terms before all else.

        // Now we just fill up the list of authors more or less randomly
        $counter = 0;
        foreach ($authors as $author) {
            $id = sprintf("%s", $author->getId());
            if ($counter <= $this->args['author_limit']) {
                $result[$author->getIndexedName()] = $author->getId();
                $counter++;
            } else if (in_array($id, $this->author_ids)) {
                $result[$author->getIndexedName()] = $id;
            }
        }

        return $result;
    }

    /**
     * Returns the EID string for the given abstract
     *
     * CHANGELOG
     *
     * Changed 08.08.2018
     * Moved the whole functionality over to the new "Command" system
     *
     * @since 0.0.1.14
     *
     * @param \Scopus\Response\Abstracts $abstract
     * @return string
     */
    private function getAbtractEid(Abstracts $abstract) {
        $coredata = $abstract->getCoredata();
        // This is a hack to access protected fields in PHP. This is needed because there is no public getter function
        // for the eid
        $closure = function () { return $this->data; };
        $data = Closure::bind($closure, $coredata, AbstractCoredata::class)();
        if ( array_key_exists('eid', $data) ){
            return $data['eid'];
        } else {
            return '';
        }
    }

    /**
     * Returns the list of tags for the given abstract object
     *
     * CHANGELOG
     *
     * @since 0.0.1.14
     *
     * @param \Scopus\Response\Abstracts $abstract
     * @return array
     */
    private function getTags(Abstracts $abstract) {
        $closure = function() { return $this->data; };
        $data = Closure::bind($closure, $abstract, Abstracts::class)();

        try {
            if ( array_key_exists('idxterms', $data) ) {
                $mainterm = $data['idxterms']['mainterm'];
                $tags = array();
                foreach ( $mainterm as $entry ){
                    $tag = $entry['$'];
                    $tags[] = $tag;
                }
                return $tags;
            } else {
                return array();
            }
        } catch (Exception $e) {
            $this->log->error('THERE WAS A PROBLEM WITH GETTING THE TAGS OF ' . $this->scopus_id);
            return array();
        }
    }

    /**
     * This function will return the value, which will be used for the collaboration field of the publication during
     * the insert operation
     *
     * CHANGELOG
     *
     * Added 27.01.2020
     *
     * @return string
     */
    private function getCollaboration() {
        // First thing we check, if there is a collaboration value already saved in the publication meta cache
        // if it does exists, we'll obviously be using that one
        $collaboration_exists = $this->publication_cache->keyExists($this->scopus_id, 'collaboration');
        if ($collaboration_exists) {
            return $this->publication_cache->readMeta($this->scopus_id, 'collaboration');  // type: string
        }

        // In case there is no value for it yet, we will use a specific strategy to determine if the publication
        // in question is a collaboration paper (ANY) or not (NONE)
        return $this->guessCollaboration();
    }

    /**
     * This method will guess whether the current publication is likely to be a collaboration paper (in which case the
     * collaboration value will be ANY symbolizing an unknown collaboration), or not (in which case the value will be
     * NONE)
     * This method makes the decision based on the amount of authors, which the publication has. If this count exceeds
     * the limit (which was given as a parameter to the fetcher object), it will be declared a collaboration.
     *
     * CHANGELOG
     *
     * Added 27.01.2020
     *
     * @return string
     */
    private function guessCollaboration() {
        $author_count = count($this->abstract->getAuthors());
        if ($author_count > $this->args['collaboration_limit']) {
            return "ANY";
        } else {
            return "NONE";
        }
    }

    // ****************************
    // META CACHE RELATED FUNCTIONS
    // ****************************

    /**
     * Given a scopus id, this function will check if the publication has been flagged with the boolean "exclude" flag
     * within the publication meta cache. If it has been flagged TRUE will be returned, otherwise false.
     * The exclude flag indicates, whether the publication has already been deleted once, and thus should not be
     * fetched again.
     * The method additionally creates a log message, informing, that the publication has been excluded.
     *
     * CHANGELOG
     *
     * Added 28.11.2019
     *
     * @param string $scopus_id
     * @return bool
     */
    public function checkCacheExclude(string $scopus_id) {
        $exclude_key = 'exclude';
        $title = $this->publication_cache->getTitle($scopus_id);

        if ($this->publication_cache->keyExists($scopus_id, $exclude_key)) {
            $exclude = $this->publication_cache->readMeta($scopus_id, $exclude_key);
            if ($exclude) {
                $this->log->info(sprintf('DISMISSED, EXCLUDED (CACHED): "%s"', $title));
                return true;
            }
        }

        return false;
    }

    /**
     * Given the scopus id, this function will retrieve the publishing date and the title from the publication meta
     * cache and compare it to the given date_limit for the fetch proćess. If the publication is too old for the limit
     * TRUE will be returned, otherwise FALSE.
     * The method additionally creates a log info message, informing, that the publication is to old.
     *
     * @param string $scopus_id
     * @return bool
     */
    public function checkCacheTooOld(string $scopus_id) {
        $date = $this->publication_cache->getPublishingDate($scopus_id);
        $title = $this->publication_cache->getTitle($scopus_id);
        $time_difference = strtotime($date) - strtotime($this->args['date_limit']);
        if ($time_difference <= 0) {
            $this->log->info(sprintf('DISMISSED, TOO OLD (CACHED): "%s" PUBLISHED "%s"', $title, $date));
            return true;
        }

        return false;
    }
}