<?php

final class DarkConsoleController extends PhabricatorController {

  protected $op;
  protected $data;

  public function shouldRequireLogin() {
    return !PhabricatorEnv::getEnvConfig('darkconsole.always-on');
  }

  public function shouldRequireEnabledUser() {
    return !PhabricatorEnv::getEnvConfig('darkconsole.always-on');
  }

  public function shouldAllowPartialSessions() {
    return true;
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $response = id(new AphrontAjaxResponse())->setDisableConsole(true);

    if (!$user->isLoggedIn()) {
      return $response;
    }

    $visible = $request->getStr('visible');
    if (strlen($visible)) {
      $user->setConsoleVisible((int)$visible);
      $user->save();
      return $response;
    }

    $tab = $request->getStr('tab');
    if (strlen($tab)) {
      $user->setConsoleTab($tab);
      $user->save();
      return $response;
    }

    return new Aphront404Response();
  }

}
