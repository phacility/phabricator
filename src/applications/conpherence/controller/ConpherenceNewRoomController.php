<?php

final class ConpherenceNewRoomController extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $title = pht('New Room');
    $e_title = true;
    $validation_exception = null;

    $conpherence = ConpherenceThread::initializeNewRoom($user);
    $participants = array();
    if ($request->isFormPost()) {
      $editor = new ConpherenceEditor();
      $xactions = array();

      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceThreadTitleTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getStr('title'));

      $participants = $request->getArr('participants');
      $participants[] = $user->getPHID();
      $participants = array_unique($participants);
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(
          ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
        ->setNewValue(array('+' => $participants));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceThreadTopicTransaction::TRANSACTIONTYPE)
        ->setNewValue($request->getStr('topic'));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($request->getStr('viewPolicy'));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($request->getStr('editPolicy'));

      try {
        $editor
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setActor($user)
          ->applyTransactions($conpherence, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$conpherence->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_title = $ex->getShortMessage(
          ConpherenceThreadTitleTransaction::TRANSACTIONTYPE);

        $conpherence->setViewPolicy($request->getStr('viewPolicy'));
        $conpherence->setEditPolicy($request->getStr('editPolicy'));
      }
    } else {
      if ($request->getStr('participant')) {
        $participants[] = $request->getStr('participant');
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($conpherence)
      ->execute();

    $submit_uri = $this->getApplicationURI('new/');
    $cancel_uri = $this->getApplicationURI('search/');

    $dialog = $this->newDialog()
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setValidationException($validation_exception)
      ->setUser($user)
      ->setTitle($title)
      ->addCancelButton($cancel_uri)
      ->addSubmitButton(pht('Create Room'));

    $form = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setError($e_title)
        ->setLabel(pht('Name'))
        ->setName('title')
        ->setValue($request->getStr('title')))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Topic'))
        ->setName('topic')
        ->setValue($request->getStr('topic')))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setName('participants')
        ->setUser($user)
        ->setDatasource(new PhabricatorPeopleDatasource())
        ->setValue($participants)
        ->setLabel(pht('Other Participants')))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('viewPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('editPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicies($policies));

    $dialog->appendChild($form);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
