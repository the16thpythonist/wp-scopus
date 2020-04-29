<?php


namespace the16thpythonist\Wordpress\Scopus\Publication;

use the16thpythonist\Wordpress\Scopus\Publication\PublicationPost;
use the16thpythonist\Wordpress\Scopus\Author\AuthorObservatory;
use the16thpythonist\Wordpress\Scopus\ScopusOptions;

/**
 * Class PublicationInsertArgsBuilder
 *
 * BACKGROUND
 *
 * The general way the application works is that it request a publication object from the scopus database and based on
 * this information a new post will be inserted into the wordpress database, which represents this publication.
 * Specifically the publication posts are represented by the class PublicationPost, which offers a static method
 * "insert", which accepts an array of arguments that represent all the needed information about that publication.
 * This array of arguments is partially derived from the object returned by the api, but it also depends on the current
 * state of the wordpress database.
 *
 * To enforce separation of concerns, the class "ScopusApiPublicationAdapter" was designed to derive all of the possible
 * information from the object returned by the api. It constructs a partial, unfinished arguments array.
 *
 * This very class has the repsonsibility to construct the final arguments array, which can be used to insert a new
 * post, based on this initial array and the current state of the application, as represented by the AuthorObservatory
 * and the PublicationMetaCache.
 *
 * DESIGN CHOICE
 *
 * The choice of decoupling this behaviour in such a way was made for the following reasons:
 * 1) In the future there may be another use for the direct information derived from api object, without the info
 * dependent on the database state, and then the adapter class can be reused as is.
 * 2) The specific way the additional information is constructed may be subject to future change and in this case only
 * this class has to be changed and nothing else.
 *
 * EXAMPLE
 *
 * Consider the complete example of retrieving and api "Asbtracts" object and wanting to insert a new post based on it:
 *
 * ```php
 * // The state independent part
 * $abstracts = $api->getAbstract($scopus_id);
 * $adapter = ScopusApiPublicationAdapter($abstracts);
 * $args = $adapter->getArgs();
 *
 * // the dependent part
 * $observatory = new AuthorObservatory();
 * $cache = new PublicationMetaCache();
 * $builder = new PublicationInsertArgsBuilder($args, $observatory, $cache);
 * $args = $builder->getArgs();
 *
 * $post_id = PublicationPost::insert($args);
 * ```
 *
 * CHANGELOG
 *
 * Added 29.04.2020
 *
 * @package the16thpythonist\Wordpress\Scopus\Publication
 */
class PublicationInsertArgsBuilder
{
    public $args;
    public $observatory;
    public $author_ids;
    public $config;
    public $cache;

    const DEFAULT_CONFIG = [
        'author_limit'          => 20,
        'collaboration_limit'   => 50
    ];

    /**
     * PublicationInsertArgsBuilder constructor.
     *
     * The "args" array, which is supplied to the constructor has to contain the following keys:
     * - title:                 (string) title of the paper
     * - abstract:              (string) a short summary of the paper
     * - published:             (string) date, at which the paper was published
     * - scopus_id:             (string) The ID of the paper which scopus uses internally to identify it
     * - doi:                   (string) Digital Object Identifier of the paper
     * - eid:                   (string)
     * - issn:                  (string)
     * - journal:               (string) The name of the journal in which the paper was published
     * - volume:                (string) The volume of that journal
     * - tags:                  (array) A list of tags for the paper
     * - authors:               (array) an assoc array, whose keys are the authors names and the values the author ids
     * - author_affiliations:   (array) an assoc array. Keys are author ids and values are affiliation ids.
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @param array $args
     * @param AuthorObservatory $observatory
     * @param PublicationMetaCache $cache
     * @param array $config
     */
    public function __construct(
        array $args,
        AuthorObservatory $observatory,
        PublicationMetaCache $cache,
        array $config
    )
    {
        $this->args = $args;
        $this->observatory = $observatory;
        $this->cache = $cache;
        $this->author_ids = $this->observatory->getAuthorIDs();
        $this->config = array_replace(self::DEFAULT_CONFIG, $config);
    }

    /**
     * Returns an assoc array containing the most important data about the publication, which can be used for
     * inserting a new PublicationPost
     *
     * The returned array will have the following keys:
     * - title:                 (string) title of the paper
     * - abstract:              (string) a short summary of the paper
     * - published:             (string) date, at which the paper was published
     * - scopus_id:             (string) The ID of the paper which scopus uses internally to identify it
     * - doi:                   (string) Digital Object Identifier of the paper
     * - eid:                   (string)
     * - issn:                  (string)
     * - journal:               (string) The name of the journal in which the paper was published
     * - volume:                (string) The volume of that journal
     * - tags:                  (array) A list of tags for the paper
     * - authors:               (array) an assoc array, whose keys are the authors names and the values the author ids
     * - author_affiliations:   (array) an assoc array. Keys are author ids and values are affiliation ids.
     * - status:                (string) the publication status of the post
     * - author_count:          (int) the total amount of all authors, which have contributed to the paper
     * - collaboration:         (string) an identifier for which collaboration the paper is written for
     * - categories:            (array) a list of the categories for the paper
     * - topics:                (array) a list of the topics of the paper
     *
     * BACKGROUND
     *
     *
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return array
     */
    public function getArgs(): array {
        $categories = $this->getCategories();
        $collaboration = $this->getCollaboration();
        $additional = [
            'authors'               => $this->getAuthors(),
            'author_count'          => $this->getAuthorCount(),
            'author_affiliations'   => $this->getAuthorAffiliations(),
            'categories'            => $categories,
            'collaboration'         => $collaboration,
            'topics'                => $this->getTopics($categories),
            'status'                => $this->getStatus($collaboration)
        ];

        return array_merge($this->args, $additional);
    }

    // ARGS AUGMENTATION METHODS
    // *************************
    // The whole point of this class is to augment the args array, gradually adding new key value pairs.
    // These following methods do exactly that.

    /**
     * Returns an assoc array, with the keys being the names of the authors and the values being the string ids
     *
     * BACKGROUND
     *
     * This whole class is supposed to work an an "args" array, that was already created from the raw object returned
     * by the scopus api. As such this array already contains an array of authors. This array of authors will be saved
     * as a meta attribute to the publication posts. The problem being that author lists can be very long, sometimes
     * easily reaching into the hundreds and that would a) impact the efficiency of the insert operation for a new
     * post and b) unecessarily clutter the wordpress database.
     *
     * So what this method does is limiting the amount of authors in the array to the amount specified by the
     * "author_limit" key in the config array for the builder. But it does so in two steps:
     * 1) All the authors, which have worked on the paper and are observed by the wordpress system are included
     * for sure.
     * 2) After all these have been included, the array will just be filled up to the maximum amount with the other
     * authors alphabetically.
     *
     * CHANGELOG
     *
     * Added 28.04.2020
     *
     * Changed 29.04.2020
     * I just realized the way I had it before without the usage of $remaining it could have been possible to have
     * duplicate others, if one of the observed authors was in the front of the array.
     *
     * @return array
     */
    protected function getAuthors(): array {
        $result = [];
        $remaining = [];
        $counter = 0;
        foreach ($this->args['authors'] as $name => $id) {
            if ($this->isAuthorObserved($id)) {
                $result[$name] = $id;
                $counter++;
            } else {
                $remaining[$name] = $id;
            }
        }
        foreach ($remaining as $name => $id) {
            if ($counter > $this->config['author_limit']) {
                break;
            }
            $result[$name] = $id;
            $counter++;
        }
        return $result;
    }

    /**
     * Returns the amount of all authors, which have contributed to the publication
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return int
     */
    protected function getAuthorCount(): int {
        return count($this->args['author']);
    }

    /**
     * Returns an assoc array with the keys being the author ids and the values being their affiliation ids
     *
     * BACKGROUND
     *
     * Within the scopus database each author is identified by a author ID. The authors are also assigned a
     * "affiliation", which is a university or institute for which they are working. Every affiliation institute
     * is also identified by an ID. This affiliation can obviously change over time, when an author decides to
     * change jobs. But when requesting a publication, the returned object contains small snapshots of the authors
     * profile at the time of writing this publication. This also includes the affiliation ID at the time of writing.
     *
     * Now what this method does is it extracts the affiliation ids from all the authors, which are actually observed
     * by the application. This information is important to potentially filter out publications from authors, which
     * have been written by authors, before or after they were affiliated with the project/institute which is
     * maintaining the website.
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return array
     */
    protected function getAuthorAffiliations(): array {
        $result = [];
        foreach ($this->args['author_affiliations'] as $author_id => $affiliation_id) {
            if ($this->isAuthorObserved($author_id)) {
                $result[$author_id] = $affiliation_id;
            }
        }
        return $result;
    }

    /**
     * Returns an array with string category names for the publication
     *
     * BACKGROUND
     *
     * Aside from keeping track of publication posts, this application also keeps track of authors. Strictly speaking
     * is a category associated with an author. This relies on the assumption that every author is a specialist for one
     * or more fields of studies. As such each author is assigned with a string category name, which describes this
     * area of expertise.
     *
     * Now these categories will also be assigned to the publications using the following scheme: All the authors, which
     * are saved within the application (=observed) are cross referenced with the authors of the publication getting
     * an array of all the authors that ate both observed and have authored the paper. From all those authors the
     * categories are merged and all assigned to the publication.
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return array
     */
    protected function getCategories(): array {
        $publication_author_ids = $this->getPublicationAuthorIDs();
        return $this->observatory->getCategories($publication_author_ids);
    }

    /**
     * Returns the string publishing status for the publication post
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @param string $collaboration
     * @return string
     */
    protected function getStatus(string $collaboration): string {
        return ($collaboration === "ANY" ? 'draft' : 'publish');
    }

    /**
     * Returns the collaboration string for the publication
     *
     * BACKGROUND
     *
     * Some publications can be classified as "collaboration" papers. This type of paper usually has a huge amount of
     * authors, of which not all have actually authored the paper. They are only listed because they also work on the
     * bigger project to which the individual paper contributes.
     * It would make sense to recognize this type of paper so that the user of the application may choose to filter out
     * the posts about these collaboration papers.
     *
     * Now the problem is that the scopus database does not contain direct information about whether or not a paper
     * is a collaboration, so this decision has to be made otherwise:
     * If a paper has been previously unknown to the application, the status will be judged depending on a simple
     * heuristic: If the count of authors exceeds a certain limit, it will be classified as a collaboration and
     * otherwise not.
     * If the paper has been known to the application though, its entry is being looked up in the meta cache, which may
     * contain information about a collaboration. If the cache does contain this information, then that is returned as
     * the collaboration string
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return string
     */
    protected function getCollaboration(): string {
        $scopus_id = $this->args['scopus_id'];
        if ($this->cache->keyExists($scopus_id, 'collaboration')) {
            return $this->cache->readMeta($scopus_id, 'collaboration');
        }
        return $this->guessCollaboration();
    }

    /**
     * Returns an array of strings, which contains the topics of this paper, if given the categories
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @param array $categories
     * @return array
     */
    protected function getTopics(array $categories): array {
        return array_intersect(ScopusOptions::getAuthorCategories(), $categories);
    }

    // HELPER METHODS
    // **************

    /**
     * Returns a guess of the collaboration status for the publication
     *
     * BACKGROUND
     *
     * Some publications can be classified as "collaboration" papers. This type of paper usually has a huge amount of
     * authors, of which not all have actually authored the paper. They are only listed because they also work on the
     * bigger project to which the individual paper contributes.
     * It would make sense to recognize this type of paper so that the user of the application may choose to filter out
     * the posts about these collaboration papers.
     *
     * The guess of whether or not to classify the paper as a collaboration is made using a simple heuristic: If the
     * amount of authors exceeds the limit given by the "collaboration_limit" key in the config array, then the
     * collaboration classification to set to "ANY", which indicates that there may be at least some collaboration at
     * play. In this case the user will have to manually insert it. Otherwise it will be set to "NONE"
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return string
     */
    protected function guessCollaboration(): string {
        $author_count = $this->getAuthorCount();
        return ($author_count > $this->config['collaboration_limit'] ? 'ANY' : 'NONE');
    }

    /**
     * Returns a normal array, which contains the author ids of all the authors of the publication
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return array
     */
    protected function getPublicationAuthorIDs(): array {
        return array_values($this->args['authors']);
    }

    /**
     * Returns whether or not the author, identified by the given id, is observed by the wordpress system
     *
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @param string $id
     * @return bool
     */
    protected function isAuthorObserved(string $id): bool {
        return array_key_exists($id, $this->author_ids);
    }

}