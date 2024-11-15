<?php

final class PhabricatorRoleMenuItemController
  extends PhabricatorRoleController {

  public function handleRequest(AphrontRequest $request) {
    $response = $this->loadRole();
    if ($response) {
      return $response;
    }

    $viewer = $this->getViewer();
    $role = $this->getRole();

    $engine = id(new PhabricatorRoleProfileMenuEngine())
      ->setProfileObject($role)
      ->setController($this);

    $this->setProfileMenuEngine($engine);

    return $engine->buildResponse();
  }

}
