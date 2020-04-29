<?php


namespace the16thpythonist\Wordpress\Scopus\Commands;

use the16thpythonist\Command\Command;
use the16thpythonist\Command\Types\IntType;
use the16thpythonist\Command\Types\StringType;

use the16thpythonist\Wordpress\Scopus\Author\AuthorObservatory;
use the16thpythonist\Wordpress\Scopus\Publication\PublicationInsertArgsBuilder;
use the16thpythonist\Wordpress\Scopus\Publication\PublicationMetaCache;
use the16thpythonist\Wordpress\Scopus\Publication\PublicationPost;
use the16thpythonist\Wordpress\Scopus\Publication\ScopusApiPublicationAdapter;
use the16thpythonist\Wordpress\Scopus\WpScopus;
use Scopus\ScopusApi;

/**
 * Class FetchPublicationsCommand
 *
 * This class represents a background command, that can be executed on the server to fetch a single scopus publication
 * record and insert it as a PublicationPost.
 *
 * CHANGELOG
 *
 * Added 28.04.2020
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class GetSinglePublicationCommand extends Command
{
    public $params = [
        'scopus_id'             => [
            'optional'          => false,
            'type'              => StringType::class,
            'default'           => ''
        ],
        'author_limit'          => [
            'optional'          => true,
            'type'              => IntType::class,
            'default'           => 10
        ],
        'collaboration_limit'   => [
            'optional'          => true,
            'type'              => IntType::class,
            'default'           => 50
        ]
    ];

    /**
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @param array $args
     * @return mixed|void
     * @throws \Exception
     */
    public function run(array $args)
    {
        $api = $this->getApi();
        $abstract = $api->retrieveAbstract($args['scopus_id']);
        $this->log->info("Retrieved the publication from scopus");

        $adapter = new ScopusApiPublicationAdapter($abstract);
        $args = $adapter->getArgs();
        $this->log->debug("Created partial args array through adapter");

        $observatory = new AuthorObservatory();
        $cache = new PublicationMetaCache();
        $this->log->debug("Created AuthorObservatory and PublicationMetaCache objects");
        $builder = new PublicationInsertArgsBuilder($args, $observatory, $cache, $args);
        $args = $builder->getArgs();

        $post_id = PublicationPost::insert($args);
        $this->log->info(sprintf("Inserted new post with ID: %s", $post_id));
    }

    /**
     * CHANGELOG
     *
     * Added 29.04.2020
     *
     * @return ScopusApi
     */
    protected function getApi() {
        $api_key = WpScopus::$API_KEY;
        return new ScopusApi($api_key);
    }

}