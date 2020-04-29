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

    public function run(array $args)
    {
        // TODO: Implement run() method.
        // Theoretically I should be making a new class "ScopusPublicationAdapter" or something like this, which will
        // get a Scopus publication and convert it into a Post compatible format.

        $api = $this->getApi();
        $abstract = $api->retrieveAbstract($args['scopus_id']);

        $adapter = new ScopusApiPublicationAdapter($abstract);
        $args = $adapter->getArgs();

        $observatory = new AuthorObservatory();
        $cache = new PublicationMetaCache();
        $builder = new PublicationInsertArgsBuilder($args, $observatory, $cache, $args);
        $args = $builder->getArgs();

        PublicationPost::insert($args);
    }

    protected function getApi() {
        $api_key = WpScopus::$API_KEY;
        return new ScopusApi($api_key);
    }

}