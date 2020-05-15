<?php

final class PassphraseCredentialViewController extends PassphraseController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $credential = id(new PassphraseCredentialQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$credential) {
      return new Aphront404Response();
    }

    $type = $credential->getImplementation();

    $timeline = $this->buildTransactionTimeline(
      $credential,
      new PassphraseCredentialTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = pht('%s %s', $credential->getMonogram(), $credential->getName());
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($credential->getMonogram());
    $crumbs->setBorder(true);

    $header = $this->buildHeaderView($credential);
    $curtain = $this->buildCurtain($credential, $type);
    $subheader = $this->buildSubheaderView($credential);
    $content = $this->buildPropertySectionView($credential, $type);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setCurtain($curtain)
      ->setMainColumn($timeline)
      ->addPropertySection(pht('Properties'), $content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PassphraseCredential $credential) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($credential->getName())
      ->setPolicyObject($credential)
      ->setHeaderIcon('fa-user-secret');

    if ($credential->getIsDestroyed()) {
      $header->setStatus('fa-ban', 'red', pht('Destroyed'));
    }

    return $header;
  }

  private function buildSubheaderView(
    PassphraseCredential $credential) {
    $viewer = $this->getViewer();

    $author = $viewer->renderHandle($credential->getAuthorPHID())->render();
    $date = phabricator_datetime($credential->getDateCreated(), $viewer);
    $author = phutil_tag('strong', array(), $author);

    $person = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($credential->getAuthorPHID()))
      ->needProfileImage(true)
      ->executeOne();

    if (!$person) {
      return null;
    }

    $image_uri = $person->getProfileImageURI();
    $image_href = '/p/'.$credential->getUsername();

    $content = pht('Created by %s on %s.', $author, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
  }

  private function buildCurtain(
    PassphraseCredential $credential,
    PassphraseCredentialType $type) {
    $viewer = $this->getViewer();

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

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $credential,
      PhabricatorPolicyCapability::CAN_EDIT);

    $can_conduit = ($can_edit && !$is_locked);

    $curtain = $this->newCurtainView($credential);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Credential'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if (!$credential->getIsDestroyed()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Destroy Credential'))
          ->setIcon('fa-times')
          ->setHref($this->getApplicationURI("destroy/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(true));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Show Secret'))
          ->setIcon('fa-eye')
          ->setHref($this->getApplicationURI("reveal/{$id}/"))
          ->setDisabled(!$can_edit || $is_locked)
          ->setWorkflow(true));

      if ($type->hasPublicKey()) {
        $curtain->addAction(
          id(new PhabricatorActionView())
            ->setName(pht('Show Public Key'))
            ->setIcon('fa-download')
            ->setHref($this->getApplicationURI("public/{$id}/"))
            ->setWorkflow(true));
      }

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($credential_conduit_text)
          ->setIcon($credential_conduit_icon)
          ->setHref($this->getApplicationURI("conduit/{$id}/"))
          ->setDisabled(!$can_conduit)
          ->setWorkflow(true));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName($credential_lock_text)
          ->setIcon($credential_lock_icon)
          ->setHref($this->getApplicationURI("lock/{$id}/"))
          ->setDisabled(!$can_edit || $is_locked)
          ->setWorkflow(true));
    }

    return $curtain;
  }

  private function buildPropertySectionView(
    PassphraseCredential $credential,
    PassphraseCredentialType $type) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(
      pht('Credential Type'),
      $type->getCredentialTypeName());

    if ($type->shouldRequireUsername()) {
      $properties->addProperty(
        pht('Username'),
        $credential->getUsername());
    }

    $description = $credential->getDescription();
    if (strlen($description)) {
      $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $properties->addTextContent(
        new PHUIRemarkupView($viewer, $description));
    }

    return $properties;
  }

}
