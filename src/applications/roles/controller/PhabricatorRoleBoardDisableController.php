<?php

final class PhabricatorRoleBoardDisableController
  extends PhabricatorRoleBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $role_id = $request->getURIData('roleID');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->withIDs(array($role_id))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    if (!$role->getHasWorkboard()) {
      return new Aphront404Response();
    }

    $this->setRole($role);
    $id = $role->getID();

    $board_uri = $this->getApplicationURI("board/{$id}/");

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleWorkboardTransaction::TRANSACTIONTYPE)
        ->setNewValue(0);

      id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($board_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Disable Workboard'))
      ->appendParagraph(
        pht(
          'Disabling a workboard hides the board. Objects on the board '.
          'will no longer be annotated with column names in other '.
          'applications. You can restore the workboard later.'))
      ->addCancelButton($board_uri)
      ->addSubmitButton(pht('Disable Workboard'));
  }

}
