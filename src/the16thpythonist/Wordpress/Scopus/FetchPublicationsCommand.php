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

    public $params = array(
        'older_than'    => '01-01-2010'
    );

    protected function run(array $args)
    {
        $this->log->info('CREATING THE FETCHER OBJECT');
        $this->log->info(sprintf('...Fetching only publications older than "%s"', $args['older_than']));

        $fetcher_config = array(
            'date_limit'    => $args['older_than']
        );
        $fetcher = new PublicationFetcher($fetcher_config, $this->log);
        iterator_to_array($fetcher->next());
    }
}
