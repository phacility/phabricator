<?php

final class ManiphestSubpriorityController extends ManiphestController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if (!$request->validateCSRF()) {
      return new Aphront403Response();
    }

    $task = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getInt('task')))
      ->needProjectPHIDs(true)
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
        ->setViewer($viewer)
        ->withIDs(array($request->getInt('after')))
        ->executeOne();
      if (!$after_task) {
        return new Aphront404Response();
      }
      list($pri, $sub) = ManiphestTransactionEditor::getAdjacentSubpriority(
        $after_task,
        $is_after = true);
    } else {
      list($pri, $sub) = ManiphestTransactionEditor::getEdgeSubpriority(
        $request->getInt('priority'),
        $is_end = false);
    }

    $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
    $keyword = head(idx($keyword_map, $pri));

    $xactions = array();

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTaskPriorityTransaction::TRANSACTIONTYPE)
      ->setNewValue($keyword);

    $xactions[] = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTaskSubpriorityTransaction::TRANSACTIONTYPE)
      ->setNewValue($sub);

    $editor = id(new ManiphestTransactionEditor())
      ->setActor($viewer)
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
