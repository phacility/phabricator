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

    $task = id(new ManiphestTask())->load($this->id);
    if (!$task) {
      return new Aphront404Response();
    }

    switch ($this->action) {
      case 'add':
        ManiphestTransactionEditor::addCC(
          $task,
          $user);
        break;
      case 'rem':
        ManiphestTransactionEditor::removeCC(
          $task,
          $user);
        break;
      default:
        return new Aphront400Response();
    }

    return id(new AphrontRedirectResponse())->setURI('/T'.$task->getID());
  }
}
