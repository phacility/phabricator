<?php

final class PhabricatorAuthSSHKeyViewController
  extends PhabricatorAuthSSHKeyController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $id = $request->getURIData('id');

    $ssh_key = id(new PhabricatorAuthSSHKeyQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$ssh_key) {
      return new Aphront404Response();
    }

    $this->setSSHKeyObject($ssh_key->getObject());

    $title = pht('SSH Key %d', $ssh_key->getID());

    $curtain = $this->buildCurtain($ssh_key);
    $details = $this->buildPropertySection($ssh_key);

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($ssh_key->getName())
      ->setHeaderIcon('fa-key');

    if ($ssh_key->getIsActive()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'dark', pht('Deactivated'));
    }

    $header->addActionLink(
      id(new PHUIButtonView())
        ->setTag('a')
        ->setText(pht('View Active Keys'))
        ->setHref($ssh_key->getObject()->getSSHPublicKeyManagementURI($viewer))
        ->setIcon('fa-list-ul'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $ssh_key,
      new PhabricatorAuthSSHKeyTransactionQuery());
    $timeline->setShouldTerminate(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $details,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtain(PhabricatorAuthSSHKey $ssh_key) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $ssh_key,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $ssh_key->getID();

    $edit_uri = $this->getApplicationURI("sshkey/edit/{$id}/");
    $deactivate_uri = $this->getApplicationURI("sshkey/deactivate/{$id}/");

    $curtain = $this->newCurtainView($ssh_key);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit SSH Key'))
        ->setHref($edit_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Deactivate SSH Key'))
        ->setHref($deactivate_uri)
        ->setWorkflow(true)
        ->setDisabled(!$can_edit));

    return $curtain;
  }

  private function buildPropertySection(
    PhabricatorAuthSSHKey $ssh_key) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(pht('SSH Key Type'), $ssh_key->getKeyType());
    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($ssh_key->getDateCreated(), $viewer));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

}
