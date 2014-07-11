<?php

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

    $xactions = array(id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_SUBPRIORITY)
      ->setNewValue(array(
        'newPriority' => $after_pri,
        'newSubpriorityBase' => $after_sub,
        'direction' => '>')));
    $editor = id(new ManiphestTransactionEditor())
      ->setActor($user)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($task, $xactions);

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'tasks' => $this->renderSingleTask($task),
      ));
  }

}
