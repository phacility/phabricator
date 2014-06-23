<?php

final class PhabricatorWorkerTaskDetailController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new PhabricatorWorkerActiveTask())->load($this->id);
    if (!$task) {
      $task = id(new PhabricatorWorkerArchiveTask())->load($this->id);
    }

    if (!$task) {
      $title = pht('Task Does Not Exist');

      $error_view = new AphrontErrorView();
      $error_view->setTitle(pht('No Such Task'));
      $error_view->appendChild(phutil_tag(
        'p',
        array(),
        pht('This task may have recently been garbage collected.')));
      $error_view->setSeverity(AphrontErrorView::SEVERITY_NODATA);

      $content = $error_view;
    } else {
      $title = pht('Task %d', $task->getID());

      $header = id(new PHUIHeaderView())
        ->setHeader(pht('Task %d (%s)',
          $task->getID(),
          $task->getTaskClass()));

      $actions = $this->buildActionListView($task);
      $properties = $this->buildPropertyListView($task, $actions);

      $object_box = id(new PHUIObjectBoxView())
        ->setHeader($header)
        ->addPropertyList($properties);


      $retry_head = id(new PHUIHeaderView())
        ->setHeader(pht('Retries'));

      $retry_info = $this->buildRetryListView($task);

      $retry_box = id(new PHUIObjectBoxView())
        ->setHeader($retry_head)
        ->addPropertyList($retry_info);

      $content = array(
        $object_box,
        $retry_box,
      );
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $content,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildActionListView(PhabricatorWorkerTask $task) {
    $request = $this->getRequest();
    $user = $request->getUser();
    $id = $task->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObjectURI($request->getRequestURI());

    if ($task->isArchived()) {
      $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
      $can_retry = ($task->getResult() != $result_success);

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Retry Task'))
          ->setHref($this->getApplicationURI('/task/'.$id.'/retry/'))
          ->setIcon('fa-refresh')
          ->setWorkflow(true)
          ->setDisabled(!$can_retry));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Cancel Task'))
          ->setHref($this->getApplicationURI('/task/'.$id.'/cancel/'))
          ->setIcon('fa-times')
          ->setWorkflow(true));
    }

    $can_release = (!$task->isArchived()) &&
                   ($task->getLeaseOwner());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Free Lease'))
        ->setHref($this->getApplicationURI('/task/'.$id.'/release/'))
        ->setIcon('fa-unlock')
        ->setWorkflow(true)
        ->setDisabled(!$can_release));

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorWorkerTask $task,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    if ($task->isArchived()) {
      switch ($task->getResult()) {
        case PhabricatorWorkerArchiveTask::RESULT_SUCCESS:
          $status = pht('Complete');
          break;
        case PhabricatorWorkerArchiveTask::RESULT_FAILURE:
          $status = pht('Failed');
          break;
        case PhabricatorWorkerArchiveTask::RESULT_CANCELLED:
          $status = pht('Cancelled');
          break;
        default:
          throw new Exception('Unknown task status!');
      }
    } else {
      $status = pht('Queued');
    }

    $view->addProperty(
      pht('Task Status'),
      $status);

    $view->addProperty(
      pht('Task Class'),
      $task->getTaskClass());

    if ($task->getLeaseExpires()) {
      if ($task->getLeaseExpires() > time()) {
        $lease_status = pht('Leased');
      } else {
        $lease_status = pht('Lease Expired');
      }
    } else {
      $lease_status = phutil_tag('em', array(), pht('Not Leased'));
    }

    $view->addProperty(
      pht('Lease Status'),
      $lease_status);

    $view->addProperty(
      pht('Lease Owner'),
      $task->getLeaseOwner()
        ? $task->getLeaseOwner()
        : phutil_tag('em', array(), pht('None')));

    if ($task->getLeaseExpires() && $task->getLeaseOwner()) {
      $expires = ($task->getLeaseExpires() - time());
      $expires = phabricator_format_relative_time_detailed($expires);
    } else {
      $expires = phutil_tag('em', array(), pht('None'));
    }

    $view->addProperty(
      pht('Lease Expires'),
      $expires);

    if ($task->isArchived()) {
      $duration = number_format($task->getDuration()).' us';
    } else {
      $duration = phutil_tag('em', array(), pht('Not Completed'));
    }

    $view->addProperty(
      pht('Duration'),
      $duration);

    $data = id(new PhabricatorWorkerTaskData())->load($task->getDataID());
    $task->setData($data->getData());
    $worker = $task->getWorkerInstance();
    $data = $worker->renderForDisplay($viewer);

    $view->addProperty(
      pht('Data'),
      $data);

    return $view;
  }

  private function buildRetryListView(PhabricatorWorkerTask $task) {
    $view = new PHUIPropertyListView();

    $data = id(new PhabricatorWorkerTaskData())->load($task->getDataID());
    $task->setData($data->getData());
    $worker = $task->getWorkerInstance();

    $view->addProperty(
      pht('Failure Count'),
      $task->getFailureCount());

    $retry_count = $worker->getMaximumRetryCount();
    if ($retry_count === null) {
      $max_retries = phutil_tag('em', array(), pht('Retries Forever'));
      $retry_count = INF;
    } else {
      $max_retries = $retry_count;
    }

    $view->addProperty(
      pht('Maximum Retries'),
      $max_retries);

    $projection = clone $task;
    $projection->makeEphemeral();

    $next = array();
    for ($ii = $task->getFailureCount(); $ii < $retry_count; $ii++) {
      $projection->setFailureCount($ii);
      $next[] = $worker->getWaitBeforeRetry($projection);
      if (count($next) > 10) {
        break;
      }
    }

    if ($next) {
      $cumulative = 0;
      foreach ($next as $key => $duration) {
        if ($duration === null) {
          $duration = 60;
        }
        $cumulative += $duration;
        $next[$key] = phabricator_format_relative_time($cumulative);
      }
      if ($ii != $retry_count) {
        $next[] = '...';
      }
      $retries_in = implode(', ', $next);
    } else {
      $retries_in = pht('No More Retries');
    }

    $view->addProperty(
      pht('Retries After'),
      $retries_in);

    return $view;
  }

}
