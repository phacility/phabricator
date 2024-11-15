<?php

final class PhabricatorRoleArchiveController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $edit_uri = $this->getApplicationURI('manage/'.$role->getID().'/');

    if ($request->isFormPost()) {
      if ($role->isArchived()) {
        $new_status = PhabricatorRoleStatus::STATUS_ACTIVE;
      } else {
        $new_status = PhabricatorRoleStatus::STATUS_ARCHIVED;
      }

      $xactions = array();

      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleStatusTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_status);

      id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())->setURI($edit_uri);
    }

    if ($role->isArchived()) {
      $title = pht('Really activate role?');
      $body = pht('This role will become active again.');
      $button = pht('Activate Role');
    } else {
      $title = pht('Really archive role?');
      $body = pht('This role will be moved to the archive.');
      $button = pht('Archive Role');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->setTitle($title)
      ->appendChild($body)
      ->addCancelButton($edit_uri)
      ->addSubmitButton($button);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
