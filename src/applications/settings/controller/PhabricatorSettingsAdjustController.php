<?php

final class PhabricatorSettingsAdjustController
  extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $prefs = $user->loadPreferences();
    $prefs->setPreference(
      $request->getStr('key'),
      $request->getStr('value'));
    $prefs->save();

    return id(new AphrontAjaxResponse())->setContent(array());
  }
}
