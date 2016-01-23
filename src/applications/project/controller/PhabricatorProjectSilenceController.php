<?php

final class PhabricatorProjectSilenceController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $action = $request->getURIData('action');

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $edge_type = PhabricatorProjectSilencedEdgeType::EDGECONST;
    $done_uri = "/project/members/{$id}/";
    $viewer_phid = $viewer->getPHID();

    if (!$project->isUserMember($viewer_phid)) {
      return $this->newDialog()
        ->setTitle(pht('Not a Member'))
        ->appendParagraph(
          pht(
            'You are not a project member, so you do not receive mail sent '.
            'to members of this project.'))
        ->addCancelButton($done_uri);
    }

    $silenced = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $project->getPHID(),
      $edge_type);
    $silenced = array_fuse($silenced);
    $is_silenced = isset($silenced[$viewer_phid]);

    if ($request->isDialogFormPost()) {
      if ($is_silenced) {
        $edge_action = '-';
      } else {
        $edge_action = '+';
      }

      $xactions = array();
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $edge_type)
        ->setNewValue(
          array(
            $edge_action => array($viewer_phid => $viewer_phid),
          ));

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    if ($is_silenced) {
      $title = pht('Enable Mail');
      $body = pht(
        'When mail is sent to members of this project, you will receive a '.
        'copy.');
      $button = pht('Enable Project Mail');
    } else {
      $title = pht('Disable Mail');
      $body = pht(
        'When mail is sent to members of this project, you will no longer '.
        'receive a copy.');
      $button = pht('Disable Project Mail');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendParagraph($body)
      ->addCancelButton($done_uri)
      ->addSubmitButton($button);
  }

}
