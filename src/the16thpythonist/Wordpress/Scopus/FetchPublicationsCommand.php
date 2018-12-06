<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 14:56
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Command\Command;

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
class FetchPublicationsCommand extends Command
{

    public $params = array(
        'more_recent_than'      => '01-01-2010',
    );

    /**
     *
     * CHANGELOG
     *
     * Added 26.11.2018
     *
     * @param array $args
     * @return mixed|void
     */
    protected function run(array $args)
    {
        $this->log->info('CREATING THE FETCHER OBJECT');
        $this->log->info(sprintf('...Fetching only publications older than "%s"', $args['older_than']));

        $fetcher_config = array(
            'date_limit'    => $args['more_recent_than']
        );
        $fetcher = new PublicationFetcher($fetcher_config, $this->log);
        iterator_to_array($fetcher->next());
    }
}
