<?php

final class PhabricatorActivitySettingsPanel extends PhabricatorSettingsPanel {

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

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $logs = id(new PhabricatorPeopleLogQuery())
      ->setViewer($viewer)
      ->withRelatedPHIDs(array($user->getPHID()))
      ->executeWithCursorPager($pager);

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
      ->setTable($table);

    $pager_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($pager);

    return array($panel, $pager_box);
  }

}
