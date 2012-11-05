<?php

/**
 * @group console
 */
final class DarkConsoleController extends PhabricatorController {

  protected $op;
  protected $data;

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $visible = $request->getStr('visible');
    if (strlen($visible)) {
      $user->setConsoleVisible((int)$visible);
      $user->save();
      return id(new AphrontAjaxResponse())->setDisableConsole(true);
    }

    $tab = $request->getStr('tab');
    if (strlen($tab)) {
      $user->setConsoleTab($tab);
      $user->save();
      return id(new AphrontAjaxResponse())->setDisableConsole(true);
    }

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      $user->setConsoleEnabled(!$user->getConsoleEnabled());
      if ($user->getConsoleEnabled()) {
        $user->setConsoleVisible(true);
      }
      $user->save();
      if ($request->isAjax()) {
        return new AphrontRedirectResponse();
      } else {
        return id(new AphrontRedirectResponse())->setURI('/');
      }
    }

  }

}
