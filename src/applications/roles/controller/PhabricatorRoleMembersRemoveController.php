<?php

final class PhabricatorRoleMembersRemoveController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $type = $request->getURIData('type');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needWatchers(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    if ($type == 'watchers') {
      $is_watcher = true;
      $edge_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
    } else {
      if (!$role->supportsEditMembers()) {
        return new Aphront404Response();
      }

      $is_watcher = false;
      $edge_type = PhabricatorRoleRoleHasMemberEdgeType::EDGECONST;
    }

    $members_uri = $this->getApplicationURI('members/'.$role->getID().'/');
    $remove_phid = $request->getStr('phid');

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(
          array(
            '-' => array($remove_phid => $remove_phid),
          ));

      $editor = id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($members_uri);
    }

    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($remove_phid))
      ->executeOne();

    $target_name = phutil_tag('strong', array(), $handle->getName());
    $role_name = phutil_tag('strong', array(), $role->getName());

    if ($is_watcher) {
      $title = pht('Remove Watcher');
      $body = pht(
        'Remove %s as a watcher of %s?',
        $target_name,
        $role_name);
      $button = pht('Remove Watcher');
    } else {
      $title = pht('Remove Member');
      $body = pht(
        'Remove %s as a role member of %s?',
        $target_name,
        $role_name);
      $button = pht('Remove Member');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('phid', $remove_phid)
      ->appendParagraph($body)
      ->addCancelButton($members_uri)
      ->addSubmitButton($button);
  }

}
