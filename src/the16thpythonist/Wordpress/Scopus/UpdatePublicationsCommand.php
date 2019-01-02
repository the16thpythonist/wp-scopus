<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 16:42
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Command\Command;

/**
 * Class UpdatePublicationsCommand
 *
 * This command updates the publications, which are already on the system, by checking them against the given date and
 * removing all those, that are older. Also it checks all the affiliation IDs of the publications against the current
 * setting of affiliation black and whitelist of all the authors and deletes all those, that are blacklisted.
 *
 * CHANGELOG
 *
 * Added 31.12.2018
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class UpdatePublicationsCommand extends Command
{

    public $author_observatory;

    /**
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * @var array   Contains the required parameters for the command and the default values
     */
    public $params = array(
        'more_recent_than'      => '01-01-2010',
    );

    /**
     * This method gets executed, once the command has been issued through the web interface
     *
     * CHANGELOG
     *
     * Added 31.12.2018
     *
     * Changed 02.01.2019
     * Added log messages
     *
     * @param array $args
     * @return mixed|void
     */
    protected function run(array $args) {
        $this->log->info(sprintf('...Removing all publications older than "%s"', $args['more_recent_than']));

        $this->author_observatory = new AuthorObservatory();
        $this->log->info('CREATING NEW AUTHOR OBSERVATORY');

        $publication_posts = PublicationPost::getAll(TRUE, TRUE);
        $this->log->info(sprintf('LOADED ALL PUBLICATIONS "%s"', count($publication_posts)));
        /** @var PublicationPost $publication_post */
        foreach ($publication_posts as $publication_post) {

            // The time difference will become negative, when the actual publishing date of the publication lies
            // further in the past than the chosen time limit. if that is the case the publication will be removed.
            $publishing_date = $publication_post->published;

            $time_difference = strtotime($publishing_date) - strtotime($args['more_recent_than']);
            // $this->log->info(sprintf('TIME DIFFERENCE "%s"', $time_difference));
            if ($time_difference < 0) {
                $this->log->info(sprintf('REMOVED, TOO OLD: "%s"', $publication_post->title));
                wp_delete_post($publication_post->post->ID, TRUE);
                continue;
            }

            // Here we get the author affiliation array from the publication, which is an array, whose keys are the
            // author IDs and the values the affiliation ID they had during the writing of this publication. This array
            // is then used to see if those affiliations are blacklisted or not.
            $author_affiliations = $publication_post->author_affiliations;
            $check = $this->author_observatory->checkAuthorAffiliations($author_affiliations);
            if ($check < 0) {
                $this->log->info(sprintf('REMOVED, BLACKLISTED: "%s"', $publication_post->title));
                wp_delete_post($publication_post->post->ID, TRUE);
                continue;
            }

            $this->log->info(sprintf('KEEP "%s"', $publication_post->title));
        }
    }

}