<?php

final class PhabricatorProjectPanelController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $project = $this->getProject();

    $engine = id(new PhabricatorProjectProfilePanelEngine())
      ->setProfileObject($project)
      ->setController($this);

    $this->setProfilePanelEngine($engine);

    return $engine->buildResponse();
  }

}
