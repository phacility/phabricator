<?php

/**
 * @group maniphest
 */
final class ManiphestSubpriorityController extends ManiphestController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

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

    $phids = $task->getProjectPHIDs();
    if ($task->getOwnerPHID()) {
      $phids[] = $task->getOwnerPHID();
    }

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($user)
      ->loadHandles();

    $view = id(new ManiphestTaskListView())
      ->setUser($user)
      ->setShowSubpriorityControls(true)
      ->setShowBatchControls(true)
      ->setHandles($handles)
      ->setTasks(array($task));

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'tasks' => $view,
      ));
  }

}
