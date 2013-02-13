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
      switch ($this->action) {
        case 'join':
          PhabricatorProjectEditor::applyJoinProject($project, $user);
          break;
        case 'leave':
          PhabricatorProjectEditor::applyLeaveProject($project, $user);
          break;
      }
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
