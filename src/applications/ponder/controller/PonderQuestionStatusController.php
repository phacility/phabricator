<?php

final class PonderQuestionStatusController
  extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $status = $request->getURIData('status');

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    switch ($status) {
      case 'open':
        $status = PonderQuestionStatus::STATUS_OPEN;
        break;
      case 'close':
        $status = PonderQuestionStatus::STATUS_CLOSED;
        break;
      default:
        return new Aphront400Response();
    }

    $xactions = array();
    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PonderQuestionTransaction::TYPE_STATUS)
      ->setNewValue($status);

    $editor = id(new PonderQuestionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($question, $xactions);

    return id(new AphrontRedirectResponse())->setURI('/Q'.$question->getID());
  }

}
