<?php
namespace Application\Job;

use QueueableJob;
use ZendQueue\Queue as ZendQueue;
use ZendQueue\Message as ZendQueueMessage;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Page\Page;
use Exception;

class EraseDraftpage extends QueueableJob implements ApplicationAwareInterface
{
    public $jSupportsQueue = true;

    use ApplicationAwareTrait;

    public function getJobName()
    {
        return t('Erase Draft Pages');
    }

    public function getJobDescription()
    {
        return t('This job will erase all draft pages. It would be useful for those who ended up having too many draft pages.');
    }

    public function start(ZendQueue $q)
    {
        $sites = $this->app->make('site')->getList(false);
        foreach ($sites as $site) {
            $pageDrafts = Page::getDrafts($site);
            foreach ($pageDrafts as $pageDraft) {
                $q->send($pageDraft->getCollectionID());
            }
        }
    }

    public function processQueueItem(ZendQueueMessage $msg)
    {
        $pageDraft = Page::getByID($msg->body);
        if ($pageDraft->isPageDraft()) {
            $pageDraft->delete();
        } else {
            throw new Exception(t('Error occurred while getting the Page object of pID: %s', $msg->body));
        }
    }

    public function finish(ZendQueue $q)
    {
        return t('Finished erasing draft pages.');
    }
}
