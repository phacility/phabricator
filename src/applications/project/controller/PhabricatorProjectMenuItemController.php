<?php

final class PhabricatorProjectMenuItemController
  extends PhabricatorProjectController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $project = $this->getProject();

    $engine = id(new PhabricatorProjectProfileMenuEngine())
      ->setProfileObject($project)
      ->setController($this);

    $this->setProfileMenuEngine($engine);

    return $engine->buildResponse();
  }

}
