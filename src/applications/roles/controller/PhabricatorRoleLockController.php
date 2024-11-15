<?php

final class PhabricatorRoleLockController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $this->requireApplicationCapability(
      RoleCanLockRolesCapability::CAPABILITY);

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

    $done_uri = "/role/members/{$id}/";

    if (!$role->supportsEditMembers()) {
      return $this->newDialog()
        ->setTitle(pht('Membership Immutable'))
        ->appendChild(
          pht('This role does not support editing membership.'))
        ->addCancelButton($done_uri);
    }

    $is_locked = $role->getIsMembershipLocked();

    if ($request->isFormPost()) {
      $xactions = array();

      if ($is_locked) {
        $new_value = 0;
      } else {
        $new_value = 1;
      }

      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(
            PhabricatorRoleLockTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_value);

      $editor = id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($role->getIsMembershipLocked()) {
      $title = pht('Unlock Role');
      $body = pht(
        'If you unlock this role, members will be free to leave.');
      $button = pht('Unlock Role');
    } else {
      $title = pht('Lock Role');
      $body = pht(
        'If you lock this role, members will be prevented from '.
        'leaving it.');
      $button = pht('Lock Role');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addSubmitbutton($button)
      ->addCancelButton($done_uri);
  }

}
