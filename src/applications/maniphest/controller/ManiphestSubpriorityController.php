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

    $task->setSubpriority($new_sub);

    if ($after_pri != $task->getPriority()) {
      $xactions = array();
      $xactions[] = id(new ManiphestTransactionPro())
        ->setTransactionType(ManiphestTransactionPro::TYPE_PRIORITY)
        ->setNewValue($after_pri);

      $editor = id(new ManiphestTransactionEditorPro())
        ->setActor($user)
        ->setContinueOnMissingFields(true)
        ->setContinueOnNoEffect(true)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($task, $xactions);
    } else {
      $task->save();
    }

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'tasks' => $this->renderSingleTask($task),
      ));
  }

}
