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
use the16thpythonist\Wordpress\Scopus\ScopusOptions;

/**
 * Class PublicationFetcher
 *
 * BACKGROUND
 *
 * So this is how the whole WpScopus application works at the very base: Within the wordpress system you define authors
 * to be observed and based on these authors a list of all publications will be made and then these publications are
 * fetched from an online database and imported as post into the wordpress database.
 *
 * This is basically what this class does. You pass it a config array and a log object as the arguments to the
 * constructor and from there on it will work as a generator, which will assemble this list of all publications from
 * the observed authors and then in each iteration it will judge based on the configuration parameters whether to
 * import the publication or not. As it is a generator a new iteration will be invoked by the "next()" method. This
 * method will iterate until it finds a publication to be imported and then returns the post id of the newly
 * inserted wordpress post
 *
 * Caution: One call to the next() method does not equal one iteration then. It will silently dismiss all the unfit
 * publications until it finds one to be fitting.
 *
 * EXAMPLE
 *
 * ```php
 * $log = new VoidLog();
 * $fetcher = new PublicationFetcher([], $log);
 * foreach($fetcher->next() as $post_id) {
 *      echo $post_id;
 * }
 * ```
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class PublicationFetcher
{
    // This array will contain the configuration key value pairs.
    // Changed 30.04.2020
    // Renamed it from "args" to "config". There will also be a new args attribute, but that will represent the
    // insert args array for the publication post of the current iteration.
    public $config;

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

    // Added 30.04.2020
    // This array will contain the current insert args array during an iteration.
    public $args;

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
     *
     * Changed 30.04.2020
     * Changed the name to DEFAULT_CONFIG, because the "args" attribute of the class is now the insert args array for
     * the PublicationPost.
     */
    const DEFAULT_CONFIG = array(
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
     * Changed 30.04.2020
     * Changed the original args attribute to "config"
     * Initializing the new args attribute as an empty array.
     *
     * @since 0.0.0.2
     *
     * @param array $args
     * @param $log
     */
    public function __construct(array $args, $log)
    {
        $this->config = array_replace(self::DEFAULT_CONFIG, $args);
        $this->log = $log;
        $this->args = [];

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
        $this->api = new Scopus(WpScopus::$API_KEY);
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
     * Changed 30.04.2020
     * Moved the whole responsibility of extracting the insertion args array from the api response object to the
     * classes PublicationInsertArgsBuilder and ScopusApiPublicationAdapter.
     * Also moved some code to be wrapped by the methods "isPublicationOld" and "isPublicationBlacklisted".
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
            if ( !($this->config['count'] < 0) && $this->config['count'] == $this->current_count ) {
                $this->log->info(sprintf('Loop terminated after fetching "%s" pubs.', $this->current_count));
                break;
            }

            // isCacheDismissed will first check if the current publication is already present in the cache and then
            // check if it can be dismissed by the information present there.
            // This check is being made before the actual api call is being made, as it will save a lot of time waiting
            // for the network responses...
            if ($this->isCacheDismissed($scopus_id)) {
                continue;
            }

            try {
                $this->abstract = $this->api->retrieveAbstract($scopus_id);
            } catch(\Exception $e) {
                $this->log->error(sprintf('ERROR IN "%s"', $scopus_id));
                continue;
            }

            // The responsibility of this class is to destill all the necessary information from the api response
            // object into a partial arguments array
            $adapter = new ScopusApiPublicationAdapter($this->abstract);
            $this->args = $adapter->getArgs();

            // The responsibility of this class is to extend that partial array extracted from the api response and
            // extend it with all the data, which is dependent on the current state of the wordpress database, that is
            // the author observatory and the publication cache.
            $builder = new PublicationInsertArgsBuilder(
                $this->args,
                $this->author_observatory,
                $this->publication_cache,
                $this->config // The key names of the config for this class and the builder are the same, thus we can
                              // Just use this config also for the builder.
            );
            $this->args = $builder->getArgs();

            // This method will insert the current publication as a new entry into the meta cache
            $this->insertPublicationCache();

            // isPublicationOld will be true, if the publishing date of the current publication is below the date
            // defined as "date_limit" in the config.
            // isPublicationBlacklisted will use the author affiliation array to check if any of these affiliations
            // requires a blacklisting. The method will return true of that is the case
            if ($this->isPublicationOld() || $this->isPublicationBlacklisted()) {
                continue;
            }


            try{
                $this->post_id = PublicationPost::insert($this->args);
            } catch (\Error $e) {
                $this->log->error($e->getMessage());
                continue;
            }

            $this->log->info(sprintf(
                '<a href="%s">PUBLICATION "%s"</a>',
                get_the_permalink($this->post_id),
                $this->args['title']
            ));

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

    // HELPER FUNCTIONS
    // ****************

    /**
     * Returns whether or not the current publication is too old to be included.
     *
     * CHANGELOG
     *
     * Added 30.04.2020
     *
     * @return bool
     */
    public function isPublicationOld(): bool {
        $difference = strtotime($this->args['published']) - strtotime($this->config['date_limit']);
        if ($difference <= 0) {
            $this->log->info(sprintf(
                'DISMISSED, TOO OLD: "%s" PUBLISHED "%s"',
                $this->args['title'],
                $this->args['published']
            ));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns whether or not the current publication is considered to be on the blacklist.
     *
     * CHANGELOG
     *
     * Added 30.04.2020
     *
     * @return bool
     */
    public function isPublicationBlacklisted(): bool {
        $check = $this->author_observatory->checkAuthorAffiliations($this->args['author_affiliations']);
        if ($check <= 0) {
            $this->log->info(sprintf(
                'DISMISSED, BLACKLISTED: "%s"',
                $this->args['title']
            ));
            return true;
        } else {
            return false;
        }
    }

    // META CACHE RELATED FUNCTIONS
    // ****************************
    // These are helper functions to wrap functionality especially concerning the publication meta cache.

    /**
     * Inserts the current publication into the cache.
     *
     * CHANGELOG
     *
     * Addded 30.04.2020
     */
    public function insertPublicationCache() {
        $this->publication_cache->write(
            $this->args['scopus_id'],
            $this->args['title'],
            $this->args['published']
        );
    }

    /**
     * Returns whether or not the publication is to be dismissed due to information contained about it in the cache.
     *
     * CHANGELOG
     *
     * Added 30.04.2020
     *
     * @param string $scopus_id
     * @return bool
     */
    public function isCacheDismissed(string $scopus_id): bool {
        if ($this->publication_cache->contains($scopus_id)) {
            // First we check if the publication is too old
            if ($this->checkCacheTooOld($scopus_id)) {
                return true;
            }

            // 28.11.2019
            // Now we also need to check for the "exclude" boolean flag within the meta cache. If it is true the
            // publication should also not be loaded...
            if ($this->checkCacheExclude($scopus_id)) {
                return true;
            }
        }
        return false;
    }

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
     * CHANGELOG
     *
     * Added 29.11.2019
     *
     * @param string $scopus_id
     * @return bool
     */
    public function checkCacheTooOld(string $scopus_id) {
        $date = $this->publication_cache->getPublishingDate($scopus_id);
        $title = $this->publication_cache->getTitle($scopus_id);
        $time_difference = strtotime($date) - strtotime($this->config['date_limit']);
        if ($time_difference <= 0) {
            $this->log->info(sprintf('DISMISSED, TOO OLD (CACHED): "%s" PUBLISHED "%s"', $title, $date));
            return true;
        }

        return false;
    }

    // DEPRECATED METHODS
    // ******************
    // These methods have been used before, but are no longer required, as their functionality has been replaced

    /**
     * Returns an array, where the keys are the author names and the values are the author ids of the authors of the
     * current publication. The list is limited to the amount passed by the arguments.
     *
     * CHANGELOG
     *
     * Added 29.10.2018
     *
     * Deprecated 30.04.2020
     * The responsibility of creating the publication insert args array was outsourced to the classes
     * ScopusApiPublicationAdapter and PublicationInsertArgsBuilder...
     *
     * @deprecated
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
            if ($counter <= $this->config['author_limit']) {
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
     * Deprecated 30.04.2020
     * The responsibility of creating the publication insert args array was outsourced to the classes
     * ScopusApiPublicationAdapter and PublicationInsertArgsBuilder...
     *
     * @deprecated
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
     * Added 27.01.2020
     *
     * Deprecated 30.04.2020
     * The responsibility of creating the publication insert args array was outsourced to the classes
     * ScopusApiPublicationAdapter and PublicationInsertArgsBuilder...
     *
     * @deprecated
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
     * Deprecated 30.04.2020
     * The responsibility of creating the publication insert args array was outsourced to the classes
     * ScopusApiPublicationAdapter and PublicationInsertArgsBuilder...
     *
     * @deprecated
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
     * Deprecated 30.04.2020
     * The responsibility of creating the publication insert args array was outsourced to the classes
     * ScopusApiPublicationAdapter and PublicationInsertArgsBuilder...
     *
     * @deprecated
     *
     * @return string
     */
    private function guessCollaboration() {
        $author_count = count($this->abstract->getAuthors());
        if ($author_count > $this->config['collaboration_limit']) {
            return "ANY";
        } else {
            return "NONE";
        }
    }
}