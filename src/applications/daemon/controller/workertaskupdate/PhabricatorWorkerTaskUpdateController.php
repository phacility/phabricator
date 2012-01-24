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

class PhabricatorWorkerTaskUpdateController
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

    $task = id(new PhabricatorWorkerTask())->load($this->id);
    if (!$task) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      switch ($this->action) {
        case 'delete':
          $task->delete();
          break;
        case 'release':
          $task->setLeaseOwner(null);
          $task->setLeaseExpires(time());
          $task->save();
          break;
      }
      return id(new AphrontRedirectResponse())->setURI('/daemon/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);

    switch ($this->action) {
      case 'delete':
        $dialog->setTitle('Really delete task?');
        $dialog->appendChild(
          '<p>The work this task represents will never be performed if you '.
          'delete it. Are you sure you want to delete it?</p>');
        $dialog->addSubmitButton('Delete Task');
        break;
      case 'release':
        $dialog->setTitle('Really free task lease?');
        $dialog->appendChild(
          '<p>If the process which owns the task lease is still doing work '.
          'on it, the work may be performed twice. Are you sure you '.
          'want to free the lease?</p>');
        $dialog->addSubmitButton('Free Lease');
        break;
      default:
        return new Aphront404Response();
    }


    $dialog->addCancelButton('/daemon/');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
