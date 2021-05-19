<?php
namespace Application\Job;

use Job as AbstractJob;
use Concrete\Core\Workflow\Progress\PageProgress;
use Concrete\Core\Workflow\EmptyWorkflow;

class ClearEmptyWorkflowProgress extends AbstractJob
{
    public function getJobName()
    {
        return t("Clear Empty Workflow Progress");
    }

    public function getJobDescription()
    {
        return t("Deletes empty \"Compare Versions\" alert.");
    }

    public function run()
    {
        // retrieve all pending page workflow progresses
        $list = PageProgress::getPendingWorkflowProgressList();
        $r = $list->get();
        foreach ($r as $w) {
            $wp = $w->getWorkflowProgressObject();
            $wo = $wp->getWorkflowObject();
            if ($wo instanceof EmptyWorkflow) {
                $wp->delete();
            }
        }
    }
}
