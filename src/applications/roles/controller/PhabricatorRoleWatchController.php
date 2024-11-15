<?php

final class PhabricatorRoleWatchController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $role = id(new PhabricatorRoleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needWatchers(true)
      ->executeOne();
    if (!$role) {
      return new Aphront404Response();
    }

    $via = $request->getStr('via');
    if ($via == 'profile') {
      $done_uri = "/role/profile/{$id}/";
    } else {
      $done_uri = "/role/members/{$id}/";
    }

    $is_watcher = $role->isUserWatcher($viewer->getPHID());
    $is_ancestor = $role->isUserAncestorWatcher($viewer->getPHID());
    if ($is_ancestor && !$is_watcher) {
      $ancestor_phid = $role->getWatchedAncestorPHID($viewer->getPHID());
      $handles = $viewer->loadHandles(array($ancestor_phid));
      $ancestor_handle = $handles[$ancestor_phid];

      return $this->newDialog()
        ->setTitle(pht('Watching Ancestor'))
        ->appendParagraph(
          pht(
            'You are already watching %s, an ancestor of this role, and '.
            'are thus watching all of its subroles.',
            $ancestor_handle->renderTag()->render()))
        ->addCancelbutton($done_uri);
    }

    if ($request->isDialogFormPost()) {
      $edge_action = null;
      switch ($action) {
        case 'watch':
          $edge_action = '+';
          break;
        case 'unwatch':
          $edge_action = '-';
          break;
      }

      $type_watcher = PhabricatorObjectHasWatcherEdgeType::EDGECONST;
      $member_spec = array(
        $edge_action => array($viewer->getPHID() => $viewer->getPHID()),
      );

      $xactions = array();
      $xactions[] = id(new PhabricatorRoleTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_watcher)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorRoleTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($role, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $dialog = null;
    switch ($action) {
      case 'watch':
        $title = pht('Watch Role?');
        $body = array();
        $body[] = pht(
          'Watching a role will let you monitor it closely. You will '.
          'receive email and notifications about changes to every object '.
          'tagged with roles you watch.');
        $body[] = pht(
          'Watching a role also watches all subroles and milestones of '.
          'that role.');
        $submit = pht('Watch Role');
        break;
      case 'unwatch':
        $title = pht('Unwatch Role?');
        $body = pht(
          'You will no longer receive email or notifications about every '.
          'object associated with this role.');
        $submit = pht('Unwatch Role');
        break;
      default:
        return new Aphront404Response();
    }

    $dialog = $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('via', $via)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);

    foreach ((array)$body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return $dialog;
  }

}
