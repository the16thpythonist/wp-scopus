<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 14:56
 */

namespace the16thpythonist\Wordpress\Scopus\Commands;

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

    /**
     * CHANGELOG
     *
     * Added 26.11.2018
     *
     * Changed 31.12.2018
     * Added the parameter 'count' which is an integer value with the amount of publications to be fetched. The
     * default value is -1. A negative value will signal, that ALL the publications possible are to be fetched.
     * Added new parameter 'collaboration_threshold', which is an integer value, that determines how many authors a
     * publication has to have to classify it as a collaboration-paper.
     * Added new parameter 'author_count', integer amount of authors to be added to each PublicationPost
     *
     * @var array
     */
    public $params = array(
        'more_recent_than'          => '01-01-2010',
        'count'                     => '-1',
        'collaboration_threshold'   => '50',
        'author_count'              => '10'
    );

    /**
     *
     * CHANGELOG
     *
     * Added 26.11.2018
     *
     * Changed 31.12.2018
     * The count parameter is now passed on to the fetcher object to dictate how many publications should be fetched.
     * Added an additional log message, that displays the value of the "count" parameter.
     * 'collaboration_threshold' and 'author_count' parameters from the command call are now also passed to the
     * arguments of the fetcher.
     *
     * @param array $args
     * @return mixed|void
     */
    protected function run(array $args)
    {
        $this->log->info(sprintf('...Fetching only publications older than "%s"', $args['more_recent_than']));
        $this->log->info(sprintf('...Fetching a total of "%s" publications', $args['count']));
        $this->log->info(sprintf('...Only Adding "%s" authors to each publication post', $args['author_count']));
        $this->log->info(sprintf('...Declaring every pub. with more than "%s" as collaboration',
            $args['collaboration_threshold']));
        $this->log->info('CREATING THE FETCHER OBJECT');

        // 31.12.2018
        // The fetcher object itself implements the count behaviour, thus we just pass it the value that has been
        // given to the command.
        $fetcher_config = array(
            'date_limit'            => $args['more_recent_than'],
            'count'                 => $args['count'],
            'author_limit'          => $args['author_count'],
            'collaboration_limit'   => $args['collaboration_threshold']
        );
        $fetcher = new PublicationFetcher($fetcher_config, $this->log);
        // The next function works as a generator (the freshly fetched publications are passed out of the function via
        // 'yield').
        iterator_to_array($fetcher->next());
    }
}
