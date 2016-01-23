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

    $engine = $this->getProfilePanelEngine();
    $default = $engine->getDefaultPanel();

    switch ($default->getBuiltinKey()) {
      case PhabricatorProject::PANEL_WORKBOARD:
        $controller_object = new PhabricatorProjectBoardViewController();
        break;
      case PhabricatorProject::PANEL_PROFILE:
      default:
        $controller_object = new PhabricatorProjectProfileController();
        break;
    }

    return $this->delegateToController($controller_object);
  }

}
