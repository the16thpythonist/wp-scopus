<?php


namespace the16thpythonist\Wordpress\Scopus\Commands;

use the16thpythonist\Command\Command;
use the16thpythonist\Command\Types\StringType;

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
        'scopus_id'         => [
            'optional'      => false,
            'type'          => StringType::class,
            'default'       => ''
        ]
    ];

    public function run(array $args)
    {
        // TODO: Implement run() method.
        // Theoretically I should be making a new class "ScopusPublicationAdapter" or something like this, which will
        // get a Scopus publication and convert it into a Post compatible format.
    }
}