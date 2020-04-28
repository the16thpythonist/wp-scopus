<?php


namespace the16thpythonist\Wordpress\Scopus\Publication;

use Closure;
use Scopus\Response\AbstractCoredata;
use Scopus\Response\Abstracts;

/**
 * Class ScopusApiPublicationAdapter
 *
 * BACKGROUND
 *
 * Within this wordpress system publications are represented as post, specifically they are represented by the
 * class "PublicationPost". This class defines a static method called "insert" and it wraps all the necessary
 * steps to insert a new publication post into the wordpress database. To do that however it requires an array of
 * arguments, which has to contain special key value pairs defining the parameters of the new publication to be
 * inserted.
 * This array of arguments "args" can be partially derived from the "Abstracts" object received from the scopus api.
 *
 * That is where this class comes in. An object can be constructed by passing an "Abstracts" object to it, that being
 * the representation of a publication made by the api library used to interface with the scopus database. It is the
 * purpose of this class to wrap the functionality of deriving all the most important information from this format and
 * providing it in a manner, which is more suitable for this application.
 *
 * EXAMPLE
 *
 * Consider an example in which a new PublicationPost is to be created from an Abstracts object, which was fetched
 * from the api.
 *
 * ```php
 * $abstract = $api->getAbstract($scopusID);
 * $adapter = new ScopusApiPublicationAdapter($abstract)
 * $args = $adapter->getArgs();
 * // Further processing of args
 * $post = PublicationPost::insert($args);
 * ```
 *
 * CHANGELOG
 *
 * Added 28.04.2020
 *
 * @package the16thpythonist\Wordpress\Scopus\Publication
 */
class ScopusApiPublicationAdapter
{
    public $abstract;
    public $coredata;

    /**
     * ScopusApiPublicationAdapter constructor.
     *
     * CHANGELOG
     *
     * Added 26.04.2020
     *
     * @param Abstracts $abstract
     */
    public function __construct(Abstracts $abstract)
    {
        $this->abstract = $abstract;
        $this->coredata = $abstract->getCoredata();

    }

    /**
     * Returns an assoc array containing the most important data about the publication.
     *
     * The returned array will have the following keys:
     * - title:         (string) title of the paper
     * - abstract:      (string) a short summary of the paper
     * - published:     (string) date, at which the paper was published
     * - scopus_id:     (string) The ID of the paper which scopus uses internally to identify it
     * - doi:           (string) Digital Object Identifier of the paper
     * - eid:           (string)
     * - issn:          (string)
     * - journal:       (string) The name of the journal in which the paper was published
     * - volume:        (string) The volume of that journal
     * - tags:          (array) A list of tags for the paper
     * - authors:       (array) an assoc array, whose keys are the authors names and the values the author ids
     *
     * BACKGROUND
     *
     * Within this wordpress system publications are represented as post, specifically they are represented by the
     * class "PublicationPost". This class defines a static method called "insert" and it wraps all the necessary
     * steps to insert a new publication post into the wordpress database. To do that however it requires an array of
     * arguments, which has to contain special key value pairs defining the parameters of the new publication to be
     * inserted.
     * This array of arguments "args" can be partially derived from the "Abstracts" object received from the scopus api.
     *
     * That is where this class and especially this function comes in. It wraps the process of creating this arguments
     * array, which contains the most important data for inserting the publication, from the object returned by the api.
     * This was made as an attempt for decoupling: If the object returned by the api or requirements of the insert
     * method should ever change, these changes would only have to be implemented here in the adapter and not in any
     * specific places within the code.
     *
     * Caution: As mentioned, some of the information for inserting the publication can be derived from the Abstracts
     * object, but some cannot. Some of the information depends on the current state of the wordpress database so to
     * speak (the AuthorObservatory class in specific), but that information is not acquired here as that is none of
     * this classes concern.
     *
     * EXAMPLE
     *
     * Consider an example in which a new PublicationPost is to be created from an Abstracts object, which was fetched
     * from the api.
     *
     * ```php
     * $abstract = $api->getAbstract($scopusID);
     * $adapter = new ScopusApiPublicationAdapter($abstract)
     * $args = $adapter->getArgs();
     * // Further processing of args
     * $post = PublicationPost::insert($args);
     * ```
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * @return array
     */
    public function getArgs(): array {
        return [
            'title'                     => $this->coredata->getTitle(),
            // 'status'                    => '',
            'abstract'                  => $this->coredata->getDescription(),
            'published'                 => $this->coredata->getCoverDate(),
            'scopus_id'                 => $this->coredata->getScopusId(),
            'doi'                       => $this->coredata->getDoi(),
            'eid'                       => $this->getEid(),
            'issn'                      => $this->coredata->getIssn(),
            'journal'                   => $this->coredata->getPublicationName(),
            'volume'                    => $this->coredata->getVolume(),
            'tags'                      => $this->getTags(),
            'authors'                   => $this->getAuthors(),
            // 'author_count'              => '',
            // 'author_affiliations'       => '',
            // 'categories'                => '',
            // 'collaboration'             => '',
            // 'topics'                    => ''
        ];
    }

    // COMPUTED PROPERTIES
    // *******************

    /**
     * Returns an assoc array with the indexed author names as keys and the authors ID as value
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * @return array
     */
    protected function getAuthors(): array {
        $authors = $this->abstract->getAuthors();

        $result = [];
        foreach ($authors as $author) {
            $result[$author->getIndexedName()] = $author->getId();
        }
        return $result;
    }

    /**
     * Returns the tags of the publication as an array of strings
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * @return array
     */
    protected function getTags(): array {
        $data = $this->getProtectedFromCoredata();
        if (array_key_exists('idxterms', $data)) {
            $mainterm = $data['idxterms']['mainterm'];
            $tags = [];
            foreach ($mainterm as $entry) {
                $tags[] = $entry['$'];
            }
            return $tags;
        } else {
            return [];
        }
    }

    /**
     * Returns the EID of the publication
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * @return string
     */
    protected function getEid(): string {
        $data = $this->getProtectedFromCoredata();
        return (array_key_exists('eid', $data) ? $data['eid'] : '');
    }

    // NECESSARY UGLY HACKS
    // ********************

    /**
     * Returns the value of the protected field "data" of "$this->coredata".
     *
     * EXPLANATION
     *
     * So this is obviously a little bit of a shady hack, since it accesses a non public member of an object. But this
     * hack is necessary, since the AbstractCoredata objects do not expose public functions for all the data that has
     * been retrieved from the api!
     * Some data is actually publicly inaccessible, but still contained within a protected field.
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * @return array
     */
    protected function getProtectedFromCoredata(): array {
        $closure = function () {return $this->data;};
        return Closure::bind($closure, $this->coredata, AbstractCoredata::class)();
    }
}