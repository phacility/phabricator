<?php

final class ReleephProjectActionController extends ReleephProjectController {

  private $action;

  public function willProcessRequest(array $data) {
    parent::willProcessRequest($data);
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $action = $this->action;

    $project = id(new ReleephProjectQuery())
      ->withIDs(array($this->getReleephProject()->getID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->setViewer($viewer)
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $project_id = $project->getID();
    $project_uri = $this->getApplicationURI("project/{$project_id}/");

    switch ($action) {
      case 'deactivate':
        if ($request->isDialogFormPost()) {
          $project->deactivate($viewer)->save();
          return id(new AphrontRedirectResponse())->setURI($project_uri);
        }

        $dialog = id(new AphrontDialogView())
          ->setUser($request->getUser())
          ->setTitle(pht('Really deactivate Releeph Project?'))
          ->appendChild(phutil_tag(
            'p',
            array(),
            pht('Really deactivate the Releeph project: %s?',
            $project->getName())))
          ->addSubmitButton(pht('Deactivate Project'))
          ->addCancelButton($project_uri);

        return id(new AphrontDialogResponse())->setDialog($dialog);
      case 'activate':
        $project->setIsActive(1)->save();
        return id(new AphrontRedirectResponse())->setURI($project_uri);
    }
  }
}
