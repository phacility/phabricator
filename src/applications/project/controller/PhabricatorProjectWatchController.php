<?php

final class PhabricatorProjectWatchController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->needWatchers(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $via = $request->getStr('via');
    if ($via == 'profile') {
      $done_uri = "/project/profile/{$id}/";
    } else {
      $done_uri = "/project/members/{$id}/";
    }

    $is_watcher = $project->isUserWatcher($viewer->getPHID());
    $is_ancestor = $project->isUserAncestorWatcher($viewer->getPHID());
    if ($is_ancestor && !$is_watcher) {
      $ancestor_phid = $project->getWatchedAncestorPHID($viewer->getPHID());
      $handles = $viewer->loadHandles(array($ancestor_phid));
      $ancestor_handle = $handles[$ancestor_phid];

      return $this->newDialog()
        ->setTitle(pht('Watching Ancestor'))
        ->appendParagraph(
          pht(
            'You are already watching %s, an ancestor of this project, and '.
            'are thus watching all of its subprojects.',
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
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_watcher)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $dialog = null;
    switch ($action) {
      case 'watch':
        $title = pht('Watch Project?');
        $body = array();
        $body[] = pht(
          'Watching a project will let you monitor it closely. You will '.
          'receive email and notifications about changes to every object '.
          'tagged with projects you watch.');
        $body[] = pht(
          'Watching a project also watches all subprojects and milestones of '.
          'that project.');
        $submit = pht('Watch Project');
        break;
      case 'unwatch':
        $title = pht('Unwatch Project?');
        $body = pht(
          'You will no longer receive email or notifications about every '.
          'object associated with this project.');
        $submit = pht('Unwatch Project');
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
