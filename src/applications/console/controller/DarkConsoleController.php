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

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $response = id(new AphrontAjaxResponse())->setDisableConsole(true);

    if (!$viewer->isLoggedIn()) {
      return $response;
    }

    $visible = $request->getStr('visible');
    if (strlen($visible)) {
      $this->writeDarkConsoleSetting(
        PhabricatorDarkConsoleVisibleSetting::SETTINGKEY,
        (int)$visible);
      return $response;
    }

    $tab = $request->getStr('tab');
    if (strlen($tab)) {
      $this->writeDarkConsoleSetting(
        PhabricatorDarkConsoleTabSetting::SETTINGKEY,
        $tab);
      return $response;
    }

    return new Aphront404Response();
  }

  private function writeDarkConsoleSetting($key, $value) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $preferences = PhabricatorUserPreferences::loadUserPreferences($viewer);

    $editor = id(new PhabricatorUserPreferencesEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true);

    $xactions = array();
    $xactions[] = $preferences->newTransaction($key, $value);
    $editor->applyTransactions($preferences, $xactions);

    // Reload the user to regenerate their preferences cache. If we don't
    // do this, the "Services" tab gets misleadingly spammed up with cache
    // fills that are only filling because you toggled the console or switched
    // tabs. This makes it harder to see what's really going on, so just force
    // a cache regeneration here.
    id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($viewer->getPHID()))
      ->needUserSettings(true)
      ->execute();
  }

}
