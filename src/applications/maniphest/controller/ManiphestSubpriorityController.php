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

/**
 * @group maniphest
 */
final class ManiphestSubpriorityController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();

    if (!$request->validateCSRF()) {
      return new Aphront403Response();
    }

    $task = id(new ManiphestTask())->load($request->getInt('task'));
    if (!$task) {
      return new Aphront404Response();
    }

    if ($request->getInt('after')) {
      $after_task = id(new ManiphestTask())->load($request->getInt('after'));
      if (!$after_task) {
        return new Aphront404Response();
      }
      $after_pri = $after_task->getPriority();
      $after_sub = $after_task->getSubpriority();
    } else {
      $after_pri = $request->getInt('priority');
      $after_sub = null;
    }

    $new_sub = ManiphestTransactionEditor::getNextSubpriority(
      $after_pri,
      $after_sub);

    if ($after_pri != $task->getPriority()) {
      $xaction = new ManiphestTransaction();
      $xaction->setAuthorPHID($request->getUser()->getPHID());

      // TODO: Content source?

      $xaction->setTransactionType(ManiphestTransactionType::TYPE_PRIORITY);
      $xaction->setNewValue($after_pri);

      $editor = new ManiphestTransactionEditor();
      $editor->setActor($request->getUser());
      $editor->applyTransactions($task, array($xaction));
    }

    $task->setSubpriority($new_sub);
    $task->save();

    $pri_class = ManiphestTaskSummaryView::getPriorityClass(
      $task->getPriority());
    $class = 'maniphest-task-handle maniphest-active-handle '.$pri_class;

    $response = array(
      'className' => $class,
    );

    return id(new AphrontAjaxResponse())->setContent($response);
  }

}
