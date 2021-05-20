<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 20.11.18
 * Time: 07:18
 */

namespace the16thpythonist\Wordpress\Scopus\Commands;

// The Command base class
use function foo\func;
use the16thpythonist\Command\Command;
use the16thpythonist\KITOpen\KITOpenApi;
use the16thpythonist\KITOpen\Publication;
use the16thpythonist\Wordpress\Functions\PostUtil;

// 28.04.2020 After namespace change
use the16thpythonist\Wordpress\Scopus\Author\AuthorPost;
use the16thpythonist\Wordpress\Scopus\Publication\PublicationPost;

/**
 * Class UpdateKITOpenCommand
 *
 * This class implements the backend command to fetch all publications from the KITOpenAPI and use this information to #
 * update those publications posts in the database.
 *
 * The "update_kitopen" command fetches the publiction records for every observed author post in the database from the
 * KITOpenAPI. It then checks if any of those publications fetched from KITOpen are also present as publication posts
 * in the database, by comparing DOI identifiers. For all publication posts that have a corresponding result in KITOpen,
 * it updates the "kitopen" meta value of those publication posts with the KITOpenID. This ID can be used later on to
 * link to the corresponding KIT page of the publication.
 * For this command it is important to note that the KITOpen look up is performed based on the clear text name of the
 * author. Thus, it is important that all observed author posts have the correctly spelled name of the author
 * associated with them.
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class UpdateKITOpenCommand extends Command
{
    /**
     *
     */
    const TYPES = array(
        'BUCHAUFSATZ',
        'BUCH',
        'HOCHSCHULSCHRIFT',
        'ZEITSCHRIFTENAUFSATZ',
        'ZEITSCHRIFTENBAND',
        'PROCEEDINGSBEITRAG',
        'PROCEEDINGSBAND',
        'FORSCHUNGSBERICHT',
        'VORTRAG',
        'POSTER',
        'FORSCHUNGSDATEN',
        'MULTIMEDIA',
        'LEXIKONARTIKEL',
        'SONSTIGES'
    );

    /**
     * @var array $params
     */
    public $params = array(
        'more_recent_than'        => '2012'
    );

    public $api;
    public $publication_posts;
    public $args;

    /**
     * Runs the command.
     *
     * @param array $args The assoc array containing the arguments to the command call given through the frontend
     *      interface. For a list of possible key strings refer to the $params attribute of the class
     *
     * @return void
     */
    protected function run(array $args)
    {
        $this->args = $args;
        $this->log->info('Querying KITOpen API for observed author publications to update KITOpen IDs.');

        ini_set('memory_limit', '1024M'); // UGLY HACK - should remove at some point...
        $this->api = new KITOpenApi();
        $this->log->info('Created a new KITOpen API object.');

        $authors = AuthorPost::getAll();
        $author_count = count($authors);
        $this->log->info("Retrieved $author_count observed author posts from the database.");

        $this->publication_posts = PublicationPost::getAll(TRUE, TRUE);
        $publication_posts_count = count($this->publication_posts);
        $this->log->info("Retrieved $publication_posts_count publication posts from the database.");

        foreach ($authors as $author) {
            try {
                $this->run_for_author($author);
            } catch ( Exception $e ) {
                $error_message = $e->getMessage();
                $author_name = $author->first_name . ' ' . $author->last_name;
                $this->log->error("There was an error for author $author_name: '$error_message'");
            } catch ( Error $e ) {
                $error_message = $e->getMessage();
                $author_name = $author->first_name . ' ' . $author->last_name;
                $this->log->error("There was an error for author $author_name: '$error_message'");
            }
        }
    }

    /**
     * Creates the $args array for the KITOpen API "search" method for a given AuthorPost $author.
     *
     * @param AuthorPost $author The author for which to assemble a corresponding array of arguments to perform a serach
     *
     * @return string[] The assoc arguments array has two key value pairs. The "author" entry contains the author name
     *      for which to perform the search and the "year" entry contains the string specification for the date range
     *      of the search.
     */
    public function search_args_for_author(AuthorPost $author) {
        $year = $this->args['more_recent_than'] . '-';
        $author_name = strtoupper( $author->last_name ) . ',' . strtoupper( $author->first_name[0] ) . '*';
        // $author_name = implode(' or ', [$atuhor_names]);

        return [
            'author'            => $author_name,
            'year'              => $year,
            'limit'             => '150'
        ];
    }

    /**
     * Given the list of $publications from the KITOpen API, this method converts it into an assoc array, where the
     * keys are the DOIs of the publications and the values are the publication objects themselves.
     *
     * It is important to note, that publications without a DOI value are omitted from the result. This implies that
     * the resulting arry does not have to have the same size as the input array.
     *
     * @param array $publications A list of KITOpen/Publication objects
     *
     * @return array
     */
    public function assoc_dois_publications(array $publications) {
        // Creating an associative array, which contains the DOI as the key and the publication object as the value
        $publications_assoc = array();
        /** @var Publication $publication */
        foreach ( $publications as $publication ) {
            $doi = strtoupper( $publication->getDOI() );
            if ( $doi !== '' ) {
                $publications_assoc[$doi] = $publication;
            }
        }

        return $publications_assoc;
    }

    /**
     * Performs the update procedure for the given author post $author
     *
     * @param AuthorPost $author The author post record for which to perform the update
     */
    public function run_for_author(AuthorPost $author) {
        $search_args = $this->search_args_for_author( $author );
        $publications = $this->api->search( $search_args );

        $publication_count = count( $publications );
        $author_name = $search_args['author'];
        $this->log->info( "Retrieved $publication_count publications for author $author_name" );

        // The method "assoc_dois_publications" takes the array of all publication objects from the KITOpen API and
        // converts it into an associative array, whose keys are the DOIs of the corresponding publications (which are
        // the values). It is important to note that those publications which do not have a DOI are omitted.
        $publications_assoc = $this->assoc_dois_publications($publications);
        $publication_dois = array_keys( $publications_assoc );

        $publications_dois_count = count( $publications_assoc );
        $this->log->info( "Out of those, $publication_dois_count had valid DOIs associated with them." );

        // Iterating through all the posts and if the DOI of the post is in the list of DOIs that have been fetched
        // from KITOpen, the kit open id of that object will be added to the post
        $publication_posts_count = count( $this->publication_posts );
        $this->log->info( "Iterating all $publication_posts_count publications to find DOI matches" );

        $publication_update_count = 0;
        /** @var PublicationPost $publication_post */
        foreach ( $this->publication_posts as $publication_post ) {
            $doi = strtoupper( $publication_post->doi );
            if ( in_array($doi, $publication_dois) ) {
                // Getting the KITOpen id, that belongs to that DOI and adding it as post meta
                $publication = $publications_assoc[$doi];
                $kit_id = $publication->getID();
                update_post_meta( $publication_post->ID, 'kitopen', $kit_id );
                $this->log->info(
                    sprintf(
                        'Post "%s" Added KITOpen ID: %s',
                        PostUtil::getPermalinkHTML($publication_post->ID),
                        $kit_id
                    )
                );

                $publication_update_count = $publication_update_count + 1;
            }
        }
        $this->log->info("Performed a total of $publication_update_count out of $publications_dois_count updates.");
    }
}