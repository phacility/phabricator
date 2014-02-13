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

    $project_uri = '/project/view/'.$project->getID().'/';

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

      $type_member = PhabricatorEdgeConfig::TYPE_PROJ_MEMBER;
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
        $dialog->setTitle(pht('Really leave project?'));
        $dialog->appendChild(phutil_tag('p', array(), pht(
          'Your tremendous contributions to this project will be sorely '.
          'missed. Are you sure you want to leave?')));
        $dialog->addCancelButton($project_uri);
        $dialog->addSubmitButton(pht('Leave Project'));
        break;
      default:
        return new Aphront404Response();
    }

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
