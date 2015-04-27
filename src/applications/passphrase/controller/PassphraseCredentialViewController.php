<?php

final class PassphraseCredentialViewController extends PassphraseController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$credential) {
      return new Aphront404Response();
    }

    $type = PassphraseCredentialType::getTypeByConstant(
      $credential->getCredentialType());
    if (!$type) {
      throw new Exception(pht('Credential has invalid type "%s"!', $type));
    }

    $timeline = $this->buildTransactionTimeline(
      $credential,
      new PassphraseCredentialTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = pht('%s %s', 'K'.$credential->getID(), $credential->getName());
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb('K'.$credential->getID());

    $header = $this->buildHeaderView($credential);
    $actions = $this->buildActionView($credential, $type);
    $properties = $this->buildPropertyView($credential, $type, $actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
      ),
      array(
        'title' => $title,
      ));
  }

  private function buildHeaderView(PassphraseCredential $credential) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($credential->getName())
      ->setPolicyObject($credential);

    if ($credential->getIsDestroyed()) {
      $header->setStatus('fa-ban', 'red', pht('Destroyed'));
    }

    return $header;
  }

  private function buildActionView(
    PassphraseCredential $credential,
    PassphraseCredentialType $type) {
    $viewer = $this->getRequest()->getUser();

    $id = $credential->getID();

    $is_locked = $credential->getIsLocked();
    if ($is_locked) {
      $credential_lock_text = pht('Locked Permanently');
      $credential_lock_icon = 'fa-lock';
    } else {
      $credential_lock_text = pht('Lock Permanently');
      $credential_lock_icon = 'fa-unlock';
    }

    $allow_conduit = $credential->getAllowConduit();
    if ($allow_conduit) {
      $credential_conduit_text = pht('Prevent Conduit Access');
      $credential_conduit_icon = 'fa-ban';
    } else {
      $credential_conduit_text = pht('Allow Conduit Access');
      $credential_conduit_icon = 'fa-wrench';
    }

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI('/K'.$id)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $credential,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Credential'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if (!$credential->getIsDestroyed()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Destroy Credential'))
          ->setIcon('fa-times')
          ->setHref($this->getApplicationURI("destroy/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Show Secret'))
          ->setIcon('fa-eye')
          ->setHref($this->getApplicationURI("reveal/{$id}/"))
          ->setDisabled(!$can_edit || $is_locked)
          ->setWorkflow(true));

      if ($type->hasPublicKey()) {
        $actions->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Show Public Key'))
            ->setIcon('fa-download')
            ->setHref($this->getApplicationURI("public/{$id}/"))
            ->setDisabled(!$can_edit)
            ->setWorkflow(true));
      }

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($credential_conduit_text)
          ->setIcon($credential_conduit_icon)
          ->setHref($this->getApplicationURI("conduit/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));

      $actions->addAction(
        id(new PhabricatorActionView())
          ->setName($credential_lock_text)
          ->setIcon($credential_lock_icon)
          ->setHref($this->getApplicationURI("lock/{$id}/"))
          ->setDisabled(!$can_edit || $is_locked)
          ->setWorkflow(true));
    }


    return $actions;
  }

  private function buildPropertyView(
    PassphraseCredential $credential,
    PassphraseCredentialType $type,
    PhabricatorActionListView $actions) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($credential)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Credential Type'),
      $type->getCredentialTypeName());

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $viewer,
      $credential);

    $properties->addProperty(
      pht('Editable By'),
      $descriptions[PhabricatorPolicyCapability::CAN_EDIT]);

    $properties->addProperty(
      pht('Username'),
      $credential->getUsername());

    $used_by_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $credential->getPHID(),
      PhabricatorCredentialsUsedByObjectEdgeType::EDGECONST);

    if ($used_by_phids) {
      $properties->addProperty(
        pht('Used By'),
        $viewer->renderHandleList($used_by_phids));
    }

    $description = $credential->getDescription();
    if (strlen($description)) {
      $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $properties->addTextContent(
        PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())
            ->setContent($description),
          'default',
          $viewer));
    }

    return $properties;
  }

}
