<?php

final class PhabricatorDaemonBulkJobMonitorController
  extends PhabricatorDaemonController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $job = id(new PhabricatorWorkerBulkJobQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$job) {
      return new Aphront404Response();
    }

    // If the user clicks "Continue" on a completed job, take them back to
    // whatever application sent them here.
    if ($request->getStr('done')) {
      if ($request->isFormPost()) {
        $done_uri = $job->getDoneURI();
        return id(new AphrontRedirectResponse())->setURI($done_uri);
      }
    }

    $title = pht('Bulk Job %d', $job->getID());

    if ($job->getStatus() == PhabricatorWorkerBulkJob::STATUS_CONFIRM) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $job,
        PhabricatorPolicyCapability::CAN_EDIT);

      if ($can_edit) {
        if ($request->isFormPost()) {
          $type_status = PhabricatorWorkerBulkJobTransaction::TYPE_STATUS;

          $xactions = array();
          $xactions[] = id(new PhabricatorWorkerBulkJobTransaction())
            ->setTransactionType($type_status)
            ->setNewValue(PhabricatorWorkerBulkJob::STATUS_WAITING);

          $editor = id(new PhabricatorWorkerBulkJobEditor())
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnMissingFields(true)
            ->applyTransactions($job, $xactions);

          return id(new AphrontRedirectResponse())
            ->setURI($job->getMonitorURI());
        } else {
          return $this->newDialog()
            ->setTitle(pht('Confirm Bulk Job'))
            ->appendParagraph($job->getDescriptionForConfirm())
            ->appendParagraph(
              pht('Start work on this bulk job?'))
            ->addCancelButton($job->getManageURI(), pht('Details'))
            ->addSubmitButton(pht('Start Work'));
        }
      } else {
        return $this->newDialog()
          ->setTitle(pht('Waiting For Confirmation'))
          ->appendParagraph(
            pht(
              'This job is waiting for confirmation before work begins.'))
          ->addCancelButotn($job->getManageURI(), pht('Details'));
      }
    }


    $dialog = $this->newDialog()
      ->setTitle(pht('%s: %s', $title, $job->getStatusName()))
      ->addCancelButton($job->getManageURI(), pht('Details'));

    switch ($job->getStatus()) {
      case PhabricatorWorkerBulkJob::STATUS_WAITING:
        $dialog->appendParagraph(
          pht('This job is waiting for tasks to be queued.'));
        break;
      case PhabricatorWorkerBulkJob::STATUS_RUNNING:
        $dialog->appendParagraph(
          pht('This job is running.'));
        break;
      case PhabricatorWorkerBulkJob::STATUS_COMPLETE:
        $dialog->appendParagraph(
          pht('This job is complete.'));
        break;
    }

    $counts = $job->loadTaskStatusCounts();
    if ($counts) {
      $dialog->appendParagraph($this->renderProgress($counts));
    }

    switch ($job->getStatus()) {
      case PhabricatorWorkerBulkJob::STATUS_COMPLETE:
        $dialog->addHiddenInput('done', true);
        $dialog->addSubmitButton(pht('Continue'));
        break;
      default:
        Javelin::initBehavior('bulk-job-reload');
        break;
    }

    return $dialog;
  }

  private function renderProgress(array $counts) {
    $this->requireResource('bulk-job-css');

    $states = array(
      PhabricatorWorkerBulkTask::STATUS_DONE => array(
        'class' => 'bulk-job-progress-slice-green',
      ),
      PhabricatorWorkerBulkTask::STATUS_RUNNING => array(
        'class' => 'bulk-job-progress-slice-blue',
      ),
      PhabricatorWorkerBulkTask::STATUS_WAITING => array(
        'class' => 'bulk-job-progress-slice-empty',
      ),
      PhabricatorWorkerBulkTask::STATUS_FAIL => array(
        'class' => 'bulk-job-progress-slice-red',
      ),
    );

    $total = array_sum($counts);
    $offset = 0;
    $bars = array();
    foreach ($states as $state => $spec) {
      $size = idx($counts, $state, 0);
      if (!$size) {
        continue;
      }

      $classes = array();
      $classes[] = 'bulk-job-progress-slice';
      $classes[] = $spec['class'];

      $width = ($size / $total);
      $bars[] = phutil_tag(
        'div',
        array(
          'class' => implode(' ', $classes),
          'style' =>
            'left: '.sprintf('%.2f%%', 100 * $offset).'; '.
            'width: '.sprintf('%.2f%%', 100 * $width).';',
        ),
        '');

      $offset += $width;
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'bulk-job-progress-bar',
      ),
      $bars);
  }

}
