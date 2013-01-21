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

    return new Aphront404Response();
  }

}
