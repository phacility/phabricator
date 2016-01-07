<?php

final class PhabricatorProjectViewController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }
    $project = $this->getProject();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $controller = 'board';
    } else {
      $controller = 'profile';
    }

    switch ($controller) {
      case 'board':
        $controller_object = new PhabricatorProjectBoardViewController();
        break;
      case 'profile':
      default:
        $controller_object = new PhabricatorProjectProfileController();
        break;
    }

    return $this->delegateToController($controller_object);
  }

}
