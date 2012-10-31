<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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
      $error_view->setTitle('No Such Task');
      $error_view->appendChild(
        '<p>This task may have recently been garbage collected.</p>');
      $error_view->setSeverity(AphrontErrorView::SEVERITY_NODATA);

      $content = $error_view;
    } else {
      $title = 'Task '.$task->getID();

      $header = id(new PhabricatorHeaderView())
        ->setHeader('Task '.$task->getID().' ('.$task->getTaskClass().')');

      $actions    = $this->buildActionListView($task);
      $properties = $this->buildPropertyListView($task);

      $content = array(
        $header,
        $actions,
        $properties,
      );
    }

    $nav = $this->buildSideNavView();
    $nav->selectFilter('');
    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function buildActionListView(PhabricatorWorkerTask $task) {
    $user = $this->getRequest()->getUser();

    $view = new PhabricatorActionListView();
    $view->setUser($user);

    $id = $task->getID();

    if ($task->isArchived()) {
      $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
      $can_retry = ($task->getResult() != $result_success);

      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Retry Task'))
          ->setHref($this->getApplicationURI('/task/'.$id.'/retry/'))
          ->setIcon('undo')
          ->setWorkflow(true)
          ->setDisabled(!$can_retry));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Cancel Task'))
          ->setHref($this->getApplicationURI('/task/'.$id.'/cancel/'))
          ->setIcon('delete')
          ->setWorkflow(true));
    }

    $can_release = (!$task->isArchived()) &&
                   ($task->getLeaseOwner());

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Free Lease'))
        ->setHref($this->getApplicationURI('/task/'.$id.'/release/'))
        ->setIcon('unlock')
        ->setWorkflow(true)
        ->setDisabled(!$can_release));

    return $view;
  }

  private function buildPropertyListView(PhabricatorWorkerTask $task) {
    $view = new PhabricatorPropertyListView();

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
          throw new Exception("Unknown task status!");
      }
    } else {
      $status = pht('Queued');
    }

    $view->addProperty(
      pht('Task Status'),
      $status);

    $view->addProperty(
      pht('Task Class'),
      phutil_escape_html($task->getTaskClass()));

    if ($task->getLeaseExpires()) {
      if ($task->getLeaseExpires() > time()) {
        $lease_status = pht('Leased');
      } else {
        $lease_status = pht('Lease Expired');
      }
    } else {
      $lease_status = '<em>'.pht('Not Leased').'</em>';
    }

    $view->addProperty(
      pht('Lease Status'),
      $lease_status);

    $view->addProperty(
      pht('Lease Owner'),
      $task->getLeaseOwner()
        ? phutil_escape_html($task->getLeaseOwner())
        : '<em>'.pht('None').'</em>');

    if ($task->getLeaseExpires() && $task->getLeaseOwner()) {
      $expires = ($task->getLeaseExpires() - time());
      $expires = phabricator_format_relative_time_detailed($expires);
    } else {
      $expires = '<em>'.pht('None').'</em>';
    }

    $view->addProperty(
      pht('Lease Expires'),
      $expires);

    $view->addProperty(
      pht('Failure Count'),
      phutil_escape_html($task->getFailureCount()));

    if ($task->isArchived()) {
      $duration = phutil_escape_html(number_format($task->getDuration()).' us');
    } else {
      $duration = '<em>'.pht('Not Completed').'</em>';
    }

    $view->addProperty(
      pht('Duration'),
      $duration);

    return $view;
  }

}
