<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 14:56
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Command\Command;

class FetchPublicationsCommand extends Command
{
    protected function run(array $args)
    {
        $this->log->info('CREATING THE FETCHER OBJECT');
        $fetcher = new PublicationFetcher(array(), $this->log);
        iterator_to_array($fetcher->next());
    }
}
