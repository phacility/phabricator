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

final class PhabricatorWorkerTaskUpdateController
  extends PhabricatorDaemonController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new PhabricatorWorkerActiveTask())->load($this->id);
    if (!$task) {
      $task = id(new PhabricatorWorkerArchiveTask())->load($this->id);
    }

    if (!$task) {
      return new Aphront404Response();
    }

    $result_success = PhabricatorWorkerArchiveTask::RESULT_SUCCESS;
    $can_retry = ($task->isArchived()) &&
                 ($task->getResult() != $result_success);

    $can_cancel = !$task->isArchived();
    $can_release = (!$task->isArchived()) &&
                   ($task->getLeaseOwner());

    $next_uri = $this->getApplicationURI('/task/'.$task->getID().'/');

    if ($request->isFormPost()) {
      switch ($this->action) {
        case 'retry':
          if ($can_retry) {
            $task->unarchiveTask();
          }
          break;
        case 'cancel':
          if ($can_cancel) {
            // Forcibly break the lease if one exists, so we can archive the
            // task.
            $task->setLeaseOwner(null);
            $task->setLeaseExpires(time());

            $task->archiveTask(
              PhabricatorWorkerArchiveTask::RESULT_CANCELLED,
              0);
          }
          break;
        case 'release':
          if ($can_release) {
            $task->setLeaseOwner(null);
            $task->setLeaseExpires(time());
            $task->save();
          }
          break;
      }
      return id(new AphrontRedirectResponse())
        ->setURI($next_uri);
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);

    switch ($this->action) {
      case 'retry':
        if ($can_retry) {
          $dialog->setTitle('Really retry task?');
          $dialog->appendChild(
            '<p>The task will be put back in the queue and executed '.
            'again.</p>');
          $dialog->addSubmitButton('Retry Task');
        } else {
          $dialog->setTitle('Can Not Retry');
          $dialog->appendChild(
            '<p>Only archived, unsuccessful tasks can be retried.</p>');
        }
        break;
      case 'cancel':
        if ($can_cancel) {
          $dialog->setTitle('Really cancel task?');
          $dialog->appendChild(
            '<p>The work this task represents will never be performed if you '.
            'cancel it. Are you sure you want to cancel it?</p>');
          $dialog->addSubmitButton('Cancel Task');
        } else {
          $dialog->setTitle('Can Not Cancel');
          $dialog->appendChild(
            '<p>Only active tasks can be cancelled.</p>');
        }
        break;
      case 'release':
        if ($can_release) {
          $dialog->setTitle('Really free task lease?');
          $dialog->appendChild(
            '<p>If the process which owns the task lease is still doing work '.
            'on it, the work may be performed twice. Are you sure you '.
            'want to free the lease?</p>');
          $dialog->addSubmitButton('Free Lease');
        } else {
          $dialog->setTitle('Can Not Free Lease');
          $dialog->appendChild(
            '<p>Only active, leased tasks may have their leases freed.</p>');
        }
        break;
      default:
        return new Aphront404Response();
    }

    $dialog->addCancelButton($next_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
