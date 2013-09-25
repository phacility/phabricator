<?php

final class ManiphestSubscribeController extends ManiphestController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $task = id(new ManiphestTaskQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$task) {
      return new Aphront404Response();
    }

    $ccs = $task->getCCPHIDs();
    switch ($this->action) {
      case 'add':
        $ccs[] = $user->getPHID();
        break;
      case 'rem':
        $ccs = array_diff($ccs, array($user->getPHID()));
        break;
      default:
        return new Aphront400Response();
    }

    $xaction = id(new ManiphestTransaction())
      ->setTransactionType(ManiphestTransaction::TYPE_CCS)
      ->setNewValue($ccs);

    $editor = id(new ManiphestTransactionEditorPro())
      ->setActor($user)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($task, array($xaction));

    return id(new AphrontRedirectResponse())->setURI('/T'.$task->getID());
  }
}
