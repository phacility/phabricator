<?php

final class PhabricatorSettingsEditController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $engine = id(new PhabricatorSettingsEditEngine())
      ->setController($this);

    switch ($request->getURIData('type')) {
      case 'user':
        $user = id(new PhabricatorPeopleQuery())
          ->setViewer($viewer)
          ->withUsernames(array($request->getURIData('username')))
          ->executeOne();

        $preferences = $user->loadPreferences();

        PhabricatorPolicyFilter::requireCapability(
          $viewer,
          $preferences,
          PhabricatorPolicyCapability::CAN_EDIT);

        $engine->setTargetObject($preferences);
        break;
      default:
        return new Aphront404Response();
    }

    return $engine->buildResponse();
  }

}
