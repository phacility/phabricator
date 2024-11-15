<?php

final class PhabricatorRoleUpdateController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $capabilities = array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );

    switch ($action) {
      case 'join':
        $capabilities[] = PhabricatorPolicyCapability::CAN_JOIN;
        break;
      case 'leave':
        break;
      default:
        return new Aphront404Response();
    }

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $done_uri = "/role/members/{$id}/";

    if (!$role->supportsEditMembers()) {
      $copy = pht('Parent roles and milestones do not support adding '.
        'members. You can add members directly to any non-parent subrole.');

      return $this->newDialog()
        ->setTitle(pht('Unsupported Role'))
        ->appendParagraph($copy)
        ->addCancelButton($done_uri);
    }

    if ($request->isFormPost()) {
      $edge_action = null;
      switch ($action) {
        case 'join':
          $edge_action = '+';
          break;
        case 'leave':
          $edge_action = '-';
          break;
      }

      $type_member = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;

      $member_spec = array(
        $edge_action => array($viewer->getPHID() => $viewer->getPHID()),
      );

      $xactions = array();
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $is_locked = $role->getIsMembershipLocked();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $role,
      PhabricatorPolicyCapability::CAN_EDIT);
    $can_leave = ($can_edit || !$is_locked);

    $button = null;
    if ($action == 'leave') {
      if ($can_leave) {
        $title = pht('Leave Role');
        $body = pht(
          'Your tremendous contributions to this role will be sorely '.
          'missed. Are you sure you want to leave?');
        $button = pht('Leave Role');
      } else {
        $title = pht('Membership Locked');
        $body = pht(
          'Membership for this role is locked. You can not leave.');
      }
    } else {
      $title = pht('Join Role');
      $body = pht(
        'Join this role? You will become a member and enjoy whatever '.
        'benefits membership may confer.');
      $button = pht('Join Role');
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($done_uri);

    if ($button) {
      $dialog->addSubmitButton($button);
    }

    return $dialog;
  }

}
