<?php

final class PhabricatorSettingsPanelActivity
  extends PhabricatorSettingsPanel {

  public function isEditableByAdministrators() {
    return true;
  }

  public function getPanelKey() {
    return 'activity';
  }

  public function getPanelName() {
    return pht('Activity Logs');
  }

  public function getPanelGroup() {
    return pht('Sessions and Logs');
  }

  public function isEnabled() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $logs = id(new PhabricatorPeopleLogQuery())
      ->setViewer($viewer)
      ->withRelatedPHIDs(array($user->getPHID()))
      ->execute();

    $phids = array();
    foreach ($logs as $log) {
      $phids[] = $log->getUserPHID();
      $phids[] = $log->getActorPHID();
    }

    if ($phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($viewer)
        ->withPHIDs($phids)
        ->execute();
    } else {
      $handles = array();
    }

    $table = id(new PhabricatorUserLogView())
      ->setUser($viewer)
      ->setLogs($logs)
      ->setHandles($handles);

    $panel = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Account Activity Logs'))
      ->appendChild($table);

    return $panel;
  }

}
