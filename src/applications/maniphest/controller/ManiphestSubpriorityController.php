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

    $task = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withIDs(array($request->getInt('task')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    if ($request->getInt('after')) {
      $after_task = id(new ManiphestTaskQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('after')))
        ->executeOne();
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
      $xactions[] = id(new ManiphestTransaction())
        ->setTransactionType(ManiphestTransaction::TYPE_PRIORITY)
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
