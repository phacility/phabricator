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

    $engine = $this->getProfileMenuEngine();
    $default = $engine->getDefaultItem();

    switch ($default->getBuiltinKey()) {
      case PhabricatorProject::ITEM_WORKBOARD:
        $controller_object = new PhabricatorProjectBoardViewController();
        break;
      case PhabricatorProject::ITEM_PROFILE:
        $controller_object = new PhabricatorProjectProfileController();
        break;
      default:
        return $engine->buildResponse();
    }

    return $this->delegateToController($controller_object);
  }

}
