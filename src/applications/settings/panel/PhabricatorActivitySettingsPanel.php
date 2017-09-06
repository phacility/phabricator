<?php

final class PhabricatorActivitySettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'activity';
  }

  public function getPanelName() {
    return pht('Activity Logs');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsLogsPanelGroup::PANELGROUPKEY;
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

    $panel = $this->newBox(pht('Account Activity Logs'), $table);

    $pager_box = id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->appendChild($pager);

    return array($panel, $pager_box);
  }

  public function isManagementPanel() {
    return true;
  }

}
