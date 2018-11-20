<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 20.11.18
 * Time: 07:18
 */

namespace the16thpythonist\Wordpress\Scopus;

// The Command base class
use function foo\func;
use the16thpythonist\Command\Command;
use the16thpythonist\KITOpen\KITOpenApi;
use the16thpythonist\KITOpen\Publication;
use the16thpythonist\Wordpress\Functions\PostUtil;

/**
 * Class UpdateKITOpenCommand
 *
 * CHANGELOG
 *
 * Added 20.11.2018
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
        'newer_than'        => '2012'
    );

    /**
     * CHANGELOG
     *
     * Added 20.11.2018
     *
     * @param array $args
     *
     * @return void
     */
    protected function run(array $args)
    {
        $this->log->info('Searching for KITOpen entries for all the existing publications');

        // Forming the search query on KITOpen with all the author names of the observed authors
        $authors = AuthorPost::getAll();
        // Here we just extract the author names from all the post wrapper objects.
        // the names have the format "{last name}, {initial of first name}*", because this is the way KITOpen Api
        // expects them to be.
        $author_names = array_map(function ($a) {return $a->last_name . ', ' . $a->first_name[0] . '*'; }, $authors);
        $author_query = implode(' or ', $author_names);

        $year = $args['newer_than'] . '-';
        $type = implode(',', self::TYPES);
        $args = array(
            'year'      => $year,
            'type'      => $type,
            'author'    => $author_query
        );

        // Fetching the publications from KITOpen
        $this->log->info('Creating a new KITOpen API');
        $api = new KITOpenApi();
        $publications = $api->search($args);

        // Creating an associative array, which contains the DOI as the key and the publication object as the value
        $publications_assoc = array();
        /** @var Publication $publication */
        foreach ($publications as $publication) {
            $doi = strtoupper($publication->getDOI());
            if ($doi !== '') {
                $publications_assoc[$doi] = $publication;
            }
        }
        $publication_dois = array_keys($publications_assoc);
        // $this->log->info(var_export($publication_dois, TRUE));

        // Iterating through all the posts and if the DOI of the post is in the list of DOIs that have been fetched
        // from KITOpen, the kit open id of that object will be added to the post
        $publication_posts = PublicationPost::getAll(TRUE, TRUE);
        $this->log->info(sprintf('Iterating all the "%s" posts to find DOI matches', count($publication_posts)));
        // $this->log->info(var_export($publication_posts, TRUE));
        /** @var PublicationPost $publication_post */
        foreach ($publication_posts as $publication_post) {
            $doi = strtoupper($publication_post->doi);
            if (in_array($doi, $publication_dois)) {
                // Getting the KITOpen id, that belongs to that DOI and adding it as post meta
                $publication = $publications_assoc[$doi];
                $kit_id = $publication->getID();
                update_post_meta($publication_post->ID, 'kitopen', $kit_id);
                $this->log->info(sprintf('Post "%s" Added KITOpen ID: %s', PostUtil::getPermalinkHTML($publication_post->ID), $kit_id));
            } else {
                $this->log->info(sprintf('No KITOpen entry found for "%s"', $publication_post->title));
            }
        }
    }
}