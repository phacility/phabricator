<?php

final class PonderQuestionStatusController
  extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

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

    $view_uri = '/Q'.$question->getID();
    $v_status = $question->getStatus();

    if ($request->isFormPost()) {
      $v_status = $request->getStr('status');

      $xactions = array();
      $xactions[] = id(new PonderQuestionTransaction())
        ->setTransactionType(PonderQuestionStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($v_status);

      $editor = id(new PonderQuestionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request);

      $editor->applyTransactions($question, $xactions);

      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $radio = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Status'))
      ->setName('status')
      ->setValue($v_status);

    foreach (PonderQuestionStatus::getQuestionStatusMap() as $value => $name) {
      $description = PonderQuestionStatus::getQuestionStatusDescription($value);
      $radio->addButton($value, $name, $description);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($radio);

    return $this->newDialog()
      ->setTitle(pht('Change Question Status'))
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Submit'))
      ->addCancelButton($view_uri);

  }

}
