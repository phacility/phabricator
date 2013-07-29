<?php

final class PonderQuestionStatusController
  extends PonderController {

  private $status;
  private $id;

  public function willProcessRequest(array $data) {
    $this->status = idx($data, 'status');
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    // TODO: Use transactions.

    switch ($this->status) {
      case 'open':
        $question->setStatus(PonderQuestionStatus::STATUS_OPEN);
        break;
      case 'close':
        $question->setStatus(PonderQuestionStatus::STATUS_CLOSED);
        break;
      default:
        return new Aphront400Response();
    }

    $question->save();

    return id(new AphrontRedirectResponse())->setURI('/Q'.$question->getID());
  }

}
