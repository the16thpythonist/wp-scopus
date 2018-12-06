<?php
/**
 * Created by PhpStorm.
 * User: jonas
 * Date: 29.10.18
 * Time: 16:42
 */

namespace the16thpythonist\Wordpress\Scopus;

use the16thpythonist\Command\Command;

class DeletePublicationsCommand extends Command
{
    protected function run(array $args) {
        $this->log->info(sprintf('REMOVING *ALL* PUBLICATIONS!'));
        PublicationPost::removeAll();
    }

}