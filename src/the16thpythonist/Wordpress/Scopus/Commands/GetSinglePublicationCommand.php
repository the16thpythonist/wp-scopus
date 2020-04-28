<?php


namespace the16thpythonist\Wordpress\Scopus\Commands;

use the16thpythonist\Command\Command;
use the16thpythonist\Command\Types\StringType;

/**
 * Class FetchPublicationsCommand
 *
 * CHANGELOG
 *
 * Added 26.11.2018
 *
 * Changed 05.12.2018
 * Renamed the parameter 'older_than' to 'more_recent_than'
 *
 * @package the16thpythonist\Wordpress\Scopus
 */
class GetSinglePublicationCommand extends Command
{
    public $params = [
        'scopus id'         => [
            'optional'      => false,
            'type'          => StringType::class,
            'default'       => ''
        ]
    ];

    public function run(array $args)
    {
        // TODO: Implement run() method.
    }
}