<?php

final class PhabricatorProjectUpdateController
  extends PhabricatorProjectController {

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

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needMembers(true)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    if (!$project->supportsEditMembers()) {
      return new Aphront404Response();
    }

    $done_uri = "/project/members/{$id}/";

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

      $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;

      $member_spec = array(
        $edge_action => array($viewer->getPHID() => $viewer->getPHID()),
      );

      $xactions = array();
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())->setURI($done_uri);
    }

    $is_locked = $project->getIsMembershipLocked();
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);
    $can_leave = ($can_edit || !$is_locked);

    $button = null;
    if ($action == 'leave') {
      if ($can_leave) {
        $title = pht('Leave Project');
        $body = pht(
          'Your tremendous contributions to this project will be sorely '.
          'missed. Are you sure you want to leave?');
        $button = pht('Leave Project');
      } else {
        $title = pht('Membership Locked');
        $body = pht(
          'Membership for this project is locked. You can not leave.');
      }
    } else {
      $title = pht('Join Project');
      $body = pht(
        'Join this project? You will become a member and enjoy whatever '.
        'benefits membership may confer.');
      $button = pht('Join Project');
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
