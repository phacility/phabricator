<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorWorkerTaskDetailController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new PhabricatorWorkerTask())->load($this->id);
    if (!$task) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('No Such Task');
      $error_view->appendChild(
        '<p>This task may have recently completed.</p>');
      $error_view->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      return $this->buildStandardPageResponse(
        $error_view,
        array(
          'title' => 'Task Does Not Exist',
        ));
    }

    $data = id(new PhabricatorWorkerTaskData())->loadOneWhere(
      'id = %d',
      $task->getDataID());

    $extra = null;
    switch ($task->getTaskClass()) {
      case 'PhabricatorRepositorySvnCommitChangeParserWorker':
      case 'PhabricatorRepositoryGitCommitChangeParserWorker':
        $commit_id = idx($data->getData(), 'commitID');
        if ($commit_id) {
          $commit = id(new PhabricatorRepositoryCommit())->load($commit_id);
          if ($commit) {
            $repository = id(new PhabricatorRepository())->load(
              $commit->getRepositoryID());
            if ($repository) {
              $extra =
                "<strong>NOTE:</strong> ".
                "You can manually retry this task by running this script:".
                "<pre>".
                  "phabricator/\$ ./scripts/repository/parse_one_commit.php ".
                  "r".
                  phutil_escape_html($repository->getCallsign()).
                  phutil_escape_html($commit->getCommitIdentifier()).
                "</pre>";
            }
          }
        }
        break;
      default:
        break;
    }

    if ($data) {
      $data = json_encode($data->getData());
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('ID')
          ->setValue($task->getID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Type')
          ->setValue($task->getTaskClass()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Lease Owner')
          ->setValue($task->getLeaseOwner()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Lease Expires')
          ->setValue($task->getLeaseExpires() - time()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Failure Count')
          ->setValue($task->getFailureCount()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Data')
          ->setValue($data));

    if ($extra) {
      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('More')
          ->setValue($extra));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/daemon/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Task Detail');
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Task',
      ));
  }

}
