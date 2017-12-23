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

    // If defaults are broken somehow, serve the manage page. See T13033 for
    // discussion.
    if ($default) {
      $default_key = $default->getBuiltinKey();
    } else {
      $default_key = PhabricatorProject::ITEM_MANAGE;
    }

    switch ($default->getBuiltinKey()) {
      case PhabricatorProject::ITEM_WORKBOARD:
        $controller_object = new PhabricatorProjectBoardViewController();
        break;
      case PhabricatorProject::ITEM_PROFILE:
        $controller_object = new PhabricatorProjectProfileController();
        break;
      case PhabricatorProject::ITEM_MANAGE:
        $controller_object = new PhabricatorProjectManageController();
        break;
      default:
        return $engine->buildResponse();
    }

    return $this->delegateToController($controller_object);
  }

}
