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

      $editor = id(new PhabricatorProjectTransactionEditor($project))
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
        $body = pht(
          'Watching a project will let you monitor it closely. You will '.
          'receive email and notifications about changes to every object '.
          'associated with projects you watch.');
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

    return $this->newDialog()
      ->setTitle($title)
      ->addHiddenInput('via', $via)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($submit);
  }

}
