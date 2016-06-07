<?php

final class PhabricatorSSHKeysSettingsPanel extends PhabricatorSettingsPanel {

  public function isManagementPanel() {
    if ($this->getUser()->getIsMailingList()) {
      return false;
    }

    return true;
  }

  public function getPanelKey() {
    return 'ssh';
  }

  public function getPanelName() {
    return pht('SSH Public Keys');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    $keys = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($user->getPHID()))
      ->withIsActive(true)
      ->execute();

    $table = id(new PhabricatorAuthSSHKeyTableView())
      ->setUser($viewer)
      ->setKeys($keys)
      ->setCanEdit(true)
      ->setNoDataString(pht("You haven't added any SSH Public Keys."));

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();

    $ssh_actions = PhabricatorAuthSSHKeyTableView::newKeyActionsMenu(
      $viewer,
      $user);

    $header->setHeader(pht('SSH Public Keys'));
    $header->addActionLink($ssh_actions);

    $panel->setHeader($header);
    $panel->setTable($table);

    return $panel;
  }

}
