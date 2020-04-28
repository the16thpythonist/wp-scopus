<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 08.09.18
 * Time: 18:10
 */

namespace the16thpythonist\Wordpress\Scopus\Author;

use WP_Query;
use Scopus\ScopusApi;
use Scopus\Response\Abstracts;
use Scopus\Response\AbstractAuthor;
use Log\VoidLog;

// 28.04.2020 After the namespace change
use the16thpythonist\Wordpress\Scopus\WpScopus;


/**
 * Class AuthorObservatory
 *
 * CHANGELOG
 *
 * Added 07.09.2018
 *
 * @since 0.0.0.0
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class AuthorObservatory
{
    /**
     * @var array $authors Contains the AuthorPost wrapper objects for all the authors saved in the wordpress database
     */
    public $authors;

    /**
     * @var array $authors_map  Associative array, whose keys are the author ids of the authors saved in the wordpress
     *                          database and the values being the AuthorPost wrapper objects
     */
    public $authors_map;

    /**
     * @var ScopusApi $scopus_api   The Api wrapper used to make requests to the scopus database/website
     */
    public $scopus_api;

    public $log;

    /**
     * AuthorObservatory constructor.
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * Changed 29.10.2018
     * Changed the method of getting all the AuthorPost objects
     *
     * Changed 29.10.2018
     * Added a "log" logger attribute to the class. Which is normally set to be a VoidLog
     *
     * @since 0.0.0.0
     */
    public function __construct()
    {
        $this->log = new VoidLog();

        // Loading all the author posts and creating AuthorPost wrapper objects from them
        $this->authors = AuthorPost::getAll();

        // Creating a new API object
        $key = WpScopus::$API_KEY;
        $this->scopus_api = new ScopusApi($key);

        /*
         * The author map is supposed to be an associative array, which contains the author ids of the authors as keys
         * and the actual wrapper objects as values.
         */
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {
            foreach ($author->author_ids as $id) {
                $this->authors_map[$id] = $author;
            }
        }
    }

    /**
     * Fetches all scopus ids for all publications any of the authors has worked on from the authors scopus profile
     *
     * CHANGELOG
     *
     * Added 10.09.2018
     *
     * Changed 29.10.2018
     * Added log messages
     *
     * Changed 31.12.2018
     * Added a try catch block which in case of an error during getting the publication IDs for an author would print
     * a message to the log.
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    public function fetchScopusIDs() {

        $this->log->info('STARTING SCOPUS ID FETCH');
        $ids = array();
        /** @var AuthorPost $author */
        foreach ($this->authors as $author) {

            $this->log->info(sprintf('FETCHING PUBLICATIONS FOR AUTHOR "%s"', $author->last_name));

            // 31.12.2018
            // Added a catch block, which would log an error. Without it there would be no indication whether or not
            // the process failed, the log would just freeze.
            try {
                $_ids = $author->fetchScopusIDs($this->scopus_api);
                $this->log->info(sprintf('...FOUND TOTAL OF "%s" PUBLICATIONS"', count($_ids)));
                $ids = array_merge($_ids, $ids);
            } catch (\Exception $e) {
                $this->log->error(sprintf('THERE WAS AN ERROR WITH GETTING THE PUBLICATIONS! "%s"', $e->getMessage()));
            }


        }
        /*
         * Since the possibility of a few of the authors having worked on a publication together at times is very high
         * there might be duplicates in the list, which have to be removed.
         */
        $ids = array_unique($ids);
        return $ids;
    }

    /**
     * For a given publication Abstract this method will return if that pub is black/whitelisted
     *
     * This function is especially designed to work with the ScopusApi, as the Abstracts object used as the
     * parameter is a return data structure of the Api object.
     * Based on the authors of the given publication object, this function will first evaluate which of the
     * authors observed by this observatory were also authors of the publication and then based on these authors
     * black and whitelists it is evaluated if the status of the publication should be blacklisted (returns -1),
     * whitelisted (1) or unknown (0).
     *
     * CHANGELOG
     *
     * Added 10.09.2018
     *
     * Changed 31.12.2018
     * Rewrote the method to use the affiliation extraction methods of this class and not the AuthorPost class, because
     * extracting information from an Abstracts object is not a concern of the AuthorPost class.
     *
     * @since 0.0.0.0
     *
     * @param Abstracts $publication    The object returned by the API object as wrapper to a abstract retrieval
     *                                  response.
     * @return int
     */
    public function checkPublication(Abstracts $publication) {

        // 31.12.2018
        // We get all the affiliation IDs of the observed authors, that were part of the publications and then counter
        // check those with the blacklist and whitelist of the according AuthorPosts
        $author_affiliations = $this->getAffiliationsPublication($publication, TRUE);

        return $this->checkAuthorAffiliations($author_affiliations);
    }

    /**
     * Based on the authors
     * black and whitelists it is evaluated if the status of the publication should be blacklisted (returns -1),
     * whitelisted (1) or unknown (0).
     *
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * @param array $author_affiliations    An array, whose keys are the author IDs of the observed authors of any
     *                                      publication and the values being the affiliation IDs of those authors
     *                                      during the time of publishing that publication.
     * @return int
     */
    public function checkAuthorAffiliations(array $author_affiliations) {

        $checks = array();

        foreach ($author_affiliations as $author_id => $affiliation_id) {

            /** @var AuthorPost $author_post */
            $author_post = $this->authors_map[$author_id];
            if ($author_post->isBlacklist($affiliation_id)){
                $checks[] = -1;
            } elseif ($author_post->isWhitelist($affiliation_id)) {
                $checks[] = 1;
            }
        }

        /*
         * If even one of the authors was explicitly whitelisted for this publication, then the publication will be
         * valid overall, if that is not the case and one of them was blacklisted, the publication is not valid.
         * In any other case the status is unknown.
         */
        if (in_array(1, $checks)) {
            return 1;
        } elseif (in_array(-1, $checks)) {
            return -1;
        } else {
            return 0;
        }
    }

    /**
     * Given an Abstracts object, that has been received through the ScopusApi, this method will extract the
     * affiliation ids of all the observed authors, that were a part of the publication.
     * It will return an assoc array, where the author ids that were found in the publication are the keys and the
     * corresponding affiliation ids are the values, if the assoc parameter is true. It will return a plain array
     * with all the affiliation ids if the assoc parameter is false.
     *
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * @param Abstracts $publication    The publication in question
     * @param boolean assoc             Whether or not the returned array is supposed to be associative with the
     *                                  corresponding author ids being the keys. DEFAULT is false.
     * @return array
     */
    public function getAffiliationsPublication(Abstracts $publication, $assoc=FALSE) {

        // The array_map attribute is an array, whose keys are the author ids and the values the corresponding
        // AuthorPost wrappers
        $observed_author_ids = array_keys($this->authors_map);

        $author_affiliations = $this->getAuthorsAffiliationsPublication($publication, $observed_author_ids);
        if ($assoc) {
            return $author_affiliations;
        } else {
            return array_values($author_affiliations);
        }

    }

    /**
     * Given an Abstracts object, that has been received through the ScopusApi and an array of author_ids this method
     * will extract the affiliation id from the Abstracts object for every author in the given array (if that author
     * was actually part of the publication).
     * It will return an assoc array whose keys are the author ids and the values the affiliations ids that have been
     * extracted.
     * If the affiliation ID for an author could not be found within the Abstracts object, it will not be contained in
     * the return.
     *
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * @param Abstracts $publication    The publication for which to get the affiliation values
     * @param array $author_ids         The authors for which the affiliation ids are interesting.
     * @return array
     */
    public function getAuthorsAffiliationsPublication(Abstracts $publication, array $author_ids) {

        // First we extract all the Author objects from the actual publication object, that was sent from the
        // Scopus database. These author objects represent a state of the author, back when the publication was
        // published and thus contain the affiliation id, which was valid BACK THEN.
        $publication_authors = $publication->getAuthors();

        $affiliation_ids = array();

        foreach ($publication_authors as $publication_author) {

            // If an author ID from within the publication matches an observed author, its affiliation id is extracted
            // and added to the list which will be returned at the end
            $author_id = $publication_author->getId();
            if (in_array($author_id, $author_ids)) {

                // The AbstractAuthor class does not directly provide a method to access the affiliation ID, thus
                // we have to use the data dict (which is the exact data structure received from the JSON response of
                // the database) to access it directly.
                // The problem is, that the data dict is a protected attribute of the object, so we have to use the
                // closure trick to access it.
                $data = \Closure::bind(
                    function (){return $this->data;},
                    $publication_author,
                    AbstractAuthor::class)();
                if (array_key_exists('affiliation', $data) && array_key_exists('@id', $data['affiliation'])) {
                    $affiliation_id = $data['affiliation']['@id'];
                    $affiliation_ids[$author_id] = $affiliation_id;
                }
            }
        }

        return $affiliation_ids;

    }

    /**
     * Returns the list of all category names of all observed authors, which are authors of the given abstract
     *
     * CHANGELOG
     *
     * Added 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @param Abstracts $publication
     * @return array
     */
    public function getCategoriesPublication(Abstracts $publication) {
        // Creating a list of all the author IDS of the authors of the publication
        $authors = $publication->getAuthors();
        $author_ids = array_map(function ($a){return $a->getId(); }, $authors);

        return $this->getCategories($author_ids);
    }

    /**
     * Returns the list of all the category names of all observed authors, occuring within the given list of author ids
     *
     * CHANGELOG
     *
     * Added 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @param array $author_ids
     * @return array
     */
    public function getCategories(array $author_ids) {
        // A list of all the author ids of all the authors observed by this observatory
        $observed_authors = array_keys($this->authors_map);

        /*
         * A list of all the author ids within the given list of ids, that are also the ids of authors, observed by this
         * observatory.
         */
        $relevant_authors = array_intersect($author_ids, $observed_authors);

        $categories = array();
        foreach ($relevant_authors as $author_id) {
            /*
             * The actual post wrapper to the ID can be accessed through the assoc array 'authors_map', which stores the
             * wrapper objects as values to the ID keys. The categories will then be extended by the categories of the
             * current author, but only if they are not already in there.
             */
            /** @var AuthorPost $author */
            $author = $this->authors_map[$author_id];
            $categories = array_unique(array_merge($categories, $author->categories));
        }

        return $categories;
    }

    /**
     * Returns an array of AuthorPost objects, each wrapping a author post from the wordpress db
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * Changed 09.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    public function loadAuthors() {
        $authors = array();
        $posts = $this->loadPosts();
        foreach ($posts as $post) {
            $post_id = $post->ID;
            $author = new AuthorPost($post_id);
            $authors[] = $author;
        }
        return $authors;
    }

    /**
     * Returns an array of WP_Post objects, each post representing an author from the wordpress db
     *
     * CHANGELOG
     *
     * Added 08.09.2018
     *
     * @since 0.0.0.0
     *
     * @return array
     */
    private function loadPosts() {
        $args = array(
            'post_type'         => AuthorPost::$POST_TYPE,
            'posts_per_page'    => -1,
        );
        $query = new WP_Query($args);
        $posts = $query->get_posts();
        return $posts;
    }

    /**
     * Returns an array with all the scopus author ids of the observed authors.
     *
     * CHANGELOG
     *
     * Added 28.10.2018
     *
     * @since 0.0.0.2
     *
     * @return array
     */
    public function getAuthorIDs() {
        return array_keys($this->authors_map);
    }

    /**
     * Given a publication post, this function will return a list with all the AuthorPost objects, for all those
     * observed authors, which have worked on this very publication
     *
     * CHANGELOG
     *
     * Added 03.12.2019
     *
     * @param PublicationPost $publication_post
     * @return array
     */
    public function getObservedAuthorsPublicationPost(PublicationPost $publication_post) {
        $author_terms = $publication_post->getAuthorTerms();
        $authors = array();

        foreach ($author_terms as $term) {
            $author_id = $term->slug;
            if (array_key_exists($author_id, $this->authors_map)) {
                $author = $this->authors_map[$author_id];
                $author_name = $author->getAbbreviatedName();
                $authors[] = $author_name;
            }
        }

        return $authors;
    }
}