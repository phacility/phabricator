<?php

final class ConpherenceNewRoomController extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $title = pht('New Room');
    $e_title = true;
    $validation_exception = null;

    $conpherence = ConpherenceThread::initializeNewRoom($user);
    if ($request->isFormPost()) {

      $xactions = array();
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
        ->setNewValue(array('+' => array($user->getPHID())));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
        ->setNewValue($request->getStr('title'));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
        ->setNewValue($request->getStr('viewPolicy'));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
        ->setNewValue($request->getStr('editPolicy'));
      $xactions[] = id(new ConpherenceTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_JOIN_POLICY)
        ->setNewValue($request->getStr('joinPolicy'));

      try {
        id(new ConpherenceEditor())
          ->setContentSourceFromRequest($request)
          ->setContinueOnNoEffect(true)
          ->setActor($user)
          ->applyTransactions($conpherence, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI('/'.$conpherence->getMonogram());
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;

        $e_title = $ex->getShortMessage(ConpherenceTransactionType::TYPE_TITLE);

        $conpherence->setViewPolicy($request->getStr('viewPolicy'));
        $conpherence->setEditPolicy($request->getStr('editPolicy'));
        $conpherence->setJoinPolicy($request->getStr('joinPolicy'));
      }
    }

    $policies = id(new PhabricatorPolicyQuery())
      ->setViewer($user)
      ->setObject($conpherence)
      ->execute();

    $submit_uri = $this->getApplicationURI('room/new/');
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
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setError($e_title)
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($request->getStr('title')))
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
        ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('joinPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_JOIN)
        ->setPolicies($policies));

    $dialog->appendChild($form);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
