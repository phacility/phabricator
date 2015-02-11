<?php

final class PhabricatorProjectUpdateController
  extends PhabricatorProjectController {

  private $id;
  private $action;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $capabilities = array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );

    $process_action = false;
    switch ($this->action) {
      case 'join':
        $capabilities[] = PhabricatorPolicyCapability::CAN_JOIN;
        $process_action = $request->isFormPost();
        break;
      case 'leave':
        $process_action = $request->isDialogFormPost();
        break;
      default:
        return new Aphront404Response();
    }

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needMembers(true)
      ->requireCapabilities($capabilities)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $project_uri = $this->getApplicationURI('profile/'.$project->getID().'/');

    if ($process_action) {

      $edge_action = null;
      switch ($this->action) {
        case 'join':
          $edge_action = '+';
          break;
        case 'leave':
          $edge_action = '-';
          break;
      }

      $type_member = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
      $member_spec = array(
        $edge_action => array($user->getPHID() => $user->getPHID()),
      );

      $xactions = array();
      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $type_member)
        ->setNewValue($member_spec);

      $editor = id(new PhabricatorProjectTransactionEditor($project))
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())->setURI($project_uri);
    }

    $dialog = null;
    switch ($this->action) {
      case 'leave':
        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        if ($this->userCannotLeave($project)) {
         $dialog->setTitle(pht('You can not leave this project.'));
          $body = pht('The membership is locked for this project.');
        } else {
          $dialog->setTitle(pht('Really leave project?'));
          $body = pht(
            'Your tremendous contributions to this project will be sorely '.
            'missed. Are you sure you want to leave?');
          $dialog->addSubmitButton(pht('Leave Project'));
        }
        $dialog->appendParagraph($body);
        $dialog->addCancelButton($project_uri);
        break;
      default:
        return new Aphront404Response();
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  /**
   * This is enforced in @{class:PhabricatorProjectTransactionEditor}. We use
   * this logic to render a better form for users hitting this case.
   */
  private function userCannotLeave(PhabricatorProject $project) {
    $user = $this->getRequest()->getUser();

    return
      $project->getIsMembershipLocked() &&
      !PhabricatorPolicyFilter::hasCapability(
        $user,
        $project,
        PhabricatorPolicyCapability::CAN_EDIT);
  }
}
