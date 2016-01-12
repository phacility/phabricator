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

    return id(new PhabricatorProfilePanelEngine())
      ->setProfileObject($project)
      ->setController($this)
      ->buildResponse();
  }

}
