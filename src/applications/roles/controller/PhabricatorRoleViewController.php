<?php

final class PhabricatorRoleViewController
  extends PhabricatorRoleController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $response = $this->loadRole();
    if ($response) {
      return $response;
    }
    $role = $this->getRole();

    $engine = $this->getProfileMenuEngine();
    $default = $engine->getDefaultMenuItemConfiguration();

    // If defaults are broken somehow, serve the manage page. See T13033 for
    // discussion.
    if ($default) {
      $default_key = $default->getBuiltinKey();
    } else {
      $default_key = PhabricatorRole::ITEM_MANAGE;
    }

    switch ($default_key) {
      case PhabricatorRole::ITEM_WORKBOARD:
        $controller_object = new PhabricatorRoleBoardViewController();
        break;
      case PhabricatorRole::ITEM_PROFILE:
        $controller_object = new PhabricatorRoleProfileController();
        break;
      case PhabricatorRole::ITEM_MANAGE:
        $controller_object = new PhabricatorRoleManageController();
        break;
      default:
        return $engine->buildResponse();
    }

    return $this->delegateToController($controller_object);
  }

}
