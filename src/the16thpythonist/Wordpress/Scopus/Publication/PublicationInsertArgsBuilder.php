<?php


namespace the16thpythonist\Wordpress\Scopus\Publication;

use the16thpythonist\Wordpress\Scopus\Publication\PublicationPost;
use the16thpythonist\Wordpress\Scopus\Author\AuthorObservatory;
use the16thpythonist\Wordpress\Scopus\ScopusOptions;

class PublicationInsertArgsBuilder
{
    public $args;
    public $observatory;
    public $author_ids;
    public $config;
    public $cache;

    const DEFAULT_CONFIG = [
        'author_limit'          => 50,

    ];

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

    public function getArgs(): array {
        $categories = $this->getCategories();
        $additional = [
            'status'                => 'draft',
            'author_count'          => $this->getAuthorCount(),
            'author_affiliations'   => $this->getAuthorAffiliations(),
            'categories'            => $categories,
            'collaboration'         => $this->getCollaboration(),
            'topics'                => $this->getTopics($categories)
        ];

        return array_merge($this->args, $additional);
    }

    // ARGS AUGMENTATION METHODS
    // *************************
    // The whole point of this class is to augment the args array, gradually adding new key value pairs.
    // These following methods do exactly that.

    protected function calculateAuthors(): array {
        $result = [];
        $counter = 0;
        foreach ($this->args['authors'] as $name => $id) {
            if ($this->isAuthorObserved($id)) {
                $result[$name] = $id;
                $counter++;
            }
        }
        foreach ($this->args['authors'] as $name => $id) {
            if ($counter > $this->config['author_limit']) {
                break;
            }
            $result[$name] = $id;
            $counter++;
        }
        return $result;
    }

    protected function getAuthorCount(): int {
        return count($this->args['author']);
    }

    protected function getAuthorAffiliations(): array {
        return [];
    }

    protected function getCategories(): array {
        $publication_author_ids = $this->getPublicationAuthorIDs();
        return $this->observatory->getCategories($publication_author_ids);
    }

    protected function getCollaboration(): string {
        $scopus_id = $this->args['scopus_id'];
        if ($this->cache->keyExists($scopus_id, 'collaboration')) {
            return $this->cache->readMeta($scopus_id, 'collaboration');
        }
        return $this->guessCollaboration();
    }

    protected function getTopics(array $categories): array {
        return array_intersect(ScopusOptions::getAuthorCategories(), $categories);
    }

    // HELPER METHODS
    // **************

    protected function guessCollaboration(): string {
        $author_count = $this->getAuthorCount();
        return ($author_count > $this->config['collaboration_limit'] ? 'ANY' : 'NONE');
    }

    protected function getPublicationAuthorIDs(): array {
        return array_values($this->args['authors']);
    }

    protected function isAuthorObserved(string $id): bool {
        return array_key_exists($id, $this->author_ids);
    }

}