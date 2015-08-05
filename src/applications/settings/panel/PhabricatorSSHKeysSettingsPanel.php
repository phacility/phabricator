<?php

final class PhabricatorSSHKeysSettingsPanel extends PhabricatorSettingsPanel {

  public function isEditableByAdministrators() {
    return true;
  }

  public function getPanelKey() {
    return 'ssh';
  }

  public function getPanelName() {
    return pht('SSH Public Keys');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    if ($this->getUser()->getIsMailingList()) {
      return false;
    }

    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    $keys = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($user->getPHID()))
      ->execute();

    $table = id(new PhabricatorAuthSSHKeyTableView())
      ->setUser($viewer)
      ->setKeys($keys)
      ->setCanEdit(true)
      ->setNoDataString(pht("You haven't added any SSH Public Keys."));

    $panel = new PHUIObjectBoxView();
    $header = new PHUIHeaderView();

    $upload_icon = id(new PHUIIconView())
      ->setIconFont('fa-upload');
    $upload_button = id(new PHUIButtonView())
      ->setText(pht('Upload Public Key'))
      ->setHref('/auth/sshkey/upload/?objectPHID='.$user->getPHID())
      ->setWorkflow(true)
      ->setTag('a')
      ->setIcon($upload_icon);

    try {
      PhabricatorSSHKeyGenerator::assertCanGenerateKeypair();
      $can_generate = true;
    } catch (Exception $ex) {
      $can_generate = false;
    }

    $generate_icon = id(new PHUIIconView())
      ->setIconFont('fa-lock');
    $generate_button = id(new PHUIButtonView())
      ->setText(pht('Generate Keypair'))
      ->setHref('/auth/sshkey/generate/?objectPHID='.$user->getPHID())
      ->setTag('a')
      ->setWorkflow(true)
      ->setDisabled(!$can_generate)
      ->setIcon($generate_icon);

    $header->setHeader(pht('SSH Public Keys'));
    $header->addActionLink($generate_button);
    $header->addActionLink($upload_button);

    $panel->setHeader($header);
    $panel->setTable($table);

    return $panel;
  }

}
