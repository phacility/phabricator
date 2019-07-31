<?php

final class PhabricatorMultiFactorSettingsPanel
  extends PhabricatorSettingsPanel {

  private $isEnrollment;

  public function getPanelKey() {
    return 'multifactor';
  }

  public function getPanelName() {
    return pht('Multi-Factor Auth');
  }

  public function getPanelMenuIcon() {
    return 'fa-lock';
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
  }

  public function isMultiFactorEnrollmentPanel() {
    return true;
  }

  public function setIsEnrollment($is_enrollment) {
    $this->isEnrollment = $is_enrollment;
    return $this;
  }

  public function getIsEnrollment() {
    return $this->isEnrollment;
  }

  public function processRequest(AphrontRequest $request) {
    if ($request->getExists('new') || $request->getExists('providerPHID')) {
      return $this->processNew($request);
    }

    if ($request->getExists('edit')) {
      return $this->processEdit($request);
    }

    if ($request->getExists('delete')) {
      return $this->processDelete($request);
    }

    $user = $this->getUser();
    $viewer = $request->getUser();

    $factors = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($user->getPHID()))
      ->execute();
    $factors = msortv($factors, 'newSortVector');

    $rows = array();
    $rowc = array();

    $highlight_id = $request->getInt('id');
    foreach ($factors as $factor) {
      $provider = $factor->getFactorProvider();

      if ($factor->getID() == $highlight_id) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $status = $provider->newStatus();
      $status_icon = $status->getFactorIcon();
      $status_color = $status->getFactorColor();

      $icon = id(new PHUIIconView())
        ->setIcon("{$status_icon} {$status_color}")
        ->setTooltip(pht('Provider: %s', $status->getName()));

      $details = $provider->getConfigurationListDetails($factor, $viewer);

      $rows[] = array(
        $icon,
        javelin_tag(
          'a',
          array(
            'href' => $this->getPanelURI('?edit='.$factor->getID()),
            'sigil' => 'workflow',
          ),
          $factor->getFactorName()),
        $provider->getFactor()->getFactorShortName(),
        $provider->getDisplayName(),
        $details,
        phabricator_datetime($factor->getDateCreated(), $viewer),
        javelin_tag(
          'a',
          array(
            'href' => $this->getPanelURI('?delete='.$factor->getID()),
            'sigil' => 'workflow',
            'class' => 'small button button-grey',
          ),
          pht('Remove')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString(
      pht("You haven't added any authentication factors to your account yet."));
    $table->setHeaders(
      array(
        null,
        pht('Name'),
        pht('Type'),
        pht('Provider'),
        pht('Details'),
        pht('Created'),
        null,
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide pri',
        null,
        null,
        null,
        'right',
        'action',
      ));
    $table->setRowClasses($rowc);
    $table->setDeviceVisibility(
      array(
        true,
        true,
        false,
        false,
        false,
        false,
        true,
      ));

    $help_uri = PhabricatorEnv::getDoclink(
      'User Guide: Multi-Factor Authentication');

    $buttons = array();

    // If we're enrolling a new account in MFA, provide a small visual hint
    // that this is the button they want to click.
    if ($this->getIsEnrollment()) {
      $add_color = PHUIButtonView::BLUE;
    } else {
      $add_color = PHUIButtonView::GREY;
    }

    $can_add = (bool)$this->loadActiveMFAProviders();

    $buttons[] = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Add Auth Factor'))
      ->setHref($this->getPanelURI('?new=true'))
      ->setWorkflow(true)
      ->setDisabled(!$can_add)
      ->setColor($add_color);

    $buttons[] = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-book')
      ->setText(pht('Help'))
      ->setHref($help_uri)
      ->setColor(PHUIButtonView::GREY);

    return $this->newBox(pht('Authentication Factors'), $table, $buttons);
  }

  private function processNew(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $cancel_uri = $this->getPanelURI();

    // Check that we have providers before we send the user through the MFA
    // gate, so you don't authenticate and then immediately get roadblocked.
    $providers = $this->loadActiveMFAProviders();

    if (!$providers) {
      return $this->newDialog()
        ->setTitle(pht('No MFA Providers'))
        ->appendParagraph(
          pht(
            'This install does not have any active MFA providers configured. '.
            'At least one provider must be configured and active before you '.
            'can add new MFA factors.'))
        ->addCancelButton($cancel_uri);
    }

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $cancel_uri);

    $selected_phid = $request->getStr('providerPHID');
    if (empty($providers[$selected_phid])) {
      $selected_provider = null;
    } else {
      $selected_provider = $providers[$selected_phid];

      // Only let the user continue creating a factor for a given provider if
      // they actually pass the provider's checks.
      if (!$selected_provider->canCreateNewConfiguration($viewer)) {
        $selected_provider = null;
      }
    }

    if (!$selected_provider) {
      $menu = id(new PHUIObjectItemListView())
        ->setViewer($viewer)
        ->setBig(true)
        ->setFlush(true);

      foreach ($providers as $provider_phid => $provider) {
        $provider_uri = id(new PhutilURI($this->getPanelURI()))
          ->replaceQueryParam('providerPHID', $provider_phid);

        $is_enabled = $provider->canCreateNewConfiguration($viewer);

        $item = id(new PHUIObjectItemView())
          ->setHeader($provider->getDisplayName())
          ->setImageIcon($provider->newIconView())
          ->addAttribute($provider->getDisplayDescription());

        if ($is_enabled) {
          $item
            ->setHref($provider_uri)
            ->setClickable(true);
        } else {
          $item->setDisabled(true);
        }

        $create_description = $provider->getConfigurationCreateDescription(
          $viewer);
        if ($create_description) {
          $item->appendChild($create_description);
        }

        $menu->addItem($item);
      }

      return $this->newDialog()
        ->setTitle(pht('Choose Factor Type'))
        ->appendChild($menu)
        ->addCancelButton($cancel_uri);
    }

    // NOTE: Beyond providing guidance, this step is also providing a CSRF gate
    // on this endpoint, since prompting the user to respond to a challenge
    // sometimes requires us to push a challenge to them as a side effect (for
    // example, with SMS).
    if (!$request->isFormPost() || !$request->getBool('mfa.start')) {
      $enroll = $selected_provider->getEnrollMessage();
      if (!strlen($enroll)) {
        $enroll = $selected_provider->getEnrollDescription($viewer);
      }

      return $this->newDialog()
        ->addHiddenInput('providerPHID', $selected_provider->getPHID())
        ->addHiddenInput('mfa.start', 1)
        ->setTitle(pht('Add Authentication Factor'))
        ->appendChild(new PHUIRemarkupView($viewer, $enroll))
        ->addCancelButton($cancel_uri)
        ->addSubmitButton($selected_provider->getEnrollButtonText($viewer));
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer);

    if ($request->getBool('mfa.enroll')) {
      // Subject users to rate limiting so that it's difficult to add factors
      // by pure brute force. This is normally not much of an attack, but push
      // factor types may have side effects.
      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorAuthNewFactorAction(),
        1);
    } else {
      // Test the limit before showing the user a form, so we don't give them
      // a form which can never possibly work because it will always hit rate
      // limiting.
      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorAuthNewFactorAction(),
        0);
    }

    $config = $selected_provider->processAddFactorForm(
      $form,
      $request,
      $user);

    if ($config) {
      // If the user added a factor, give them a rate limiting point back.
      PhabricatorSystemActionEngine::willTakeAction(
        array($viewer->getPHID()),
        new PhabricatorAuthNewFactorAction(),
        -1);

      $config->save();

      // If we used a temporary token to handle synchronizing the factor,
      // revoke it now.
      $sync_token = $config->getMFASyncToken();
      if ($sync_token) {
        $sync_token->revokeToken();
      }

      $log = PhabricatorUserLog::initializeNewLog(
        $viewer,
        $user->getPHID(),
        PhabricatorAddMultifactorUserLogType::LOGTYPE);
      $log->save();

      $user->updateMultiFactorEnrollment();

      // Terminate other sessions so they must log in and survive the
      // multi-factor auth check.

      id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
        $user,
        new PhutilOpaqueEnvelope(
          $request->getCookie(PhabricatorCookies::COOKIE_SESSION)));

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?id='.$config->getID()));
    }

    return $this->newDialog()
      ->addHiddenInput('providerPHID', $selected_provider->getPHID())
      ->addHiddenInput('mfa.start', 1)
      ->addHiddenInput('mfa.enroll', 1)
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Add Authentication Factor'))
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Continue'))
      ->addCancelButton($cancel_uri);
  }

  private function processEdit(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $factor = id(new PhabricatorAuthFactorConfig())->loadOneWhere(
      'id = %d AND userPHID = %s',
      $request->getInt('edit'),
      $user->getPHID());
    if (!$factor) {
      return new Aphront404Response();
    }

    $e_name = true;
    $errors = array();
    if ($request->isFormPost()) {
      $name = $request->getStr('name');
      if (!strlen($name)) {
        $e_name = pht('Required');
        $errors[] = pht(
          'Authentication factors must have a name to identify them.');
      }

      if (!$errors) {
        $factor->setFactorName($name);
        $factor->save();

        $user->updateMultiFactorEnrollment();

        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?id='.$factor->getID()));
      }
    } else {
      $name = $factor->getFactorName();
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($name)
          ->setError($e_name));

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('edit', $factor->getID())
      ->setTitle(pht('Edit Authentication Factor'))
      ->setErrors($errors)
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Save'))
      ->addCancelButton($this->getPanelURI());

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private function processDelete(AphrontRequest $request) {
    $viewer = $request->getUser();
    $user = $this->getUser();

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    $factor = id(new PhabricatorAuthFactorConfig())->loadOneWhere(
      'id = %d AND userPHID = %s',
      $request->getInt('delete'),
      $user->getPHID());
    if (!$factor) {
      return new Aphront404Response();
    }

    if ($request->isFormPost()) {
      $factor->delete();

      $log = PhabricatorUserLog::initializeNewLog(
        $viewer,
        $user->getPHID(),
        PhabricatorRemoveMultifactorUserLogType::LOGTYPE);
      $log->save();

      $user->updateMultiFactorEnrollment();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI());
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('delete', $factor->getID())
      ->setTitle(pht('Delete Authentication Factor'))
      ->appendParagraph(
        pht(
          'Really remove the authentication factor %s from your account?',
          phutil_tag('strong', array(), $factor->getFactorName())))
      ->addSubmitButton(pht('Remove Factor'))
      ->addCancelButton($this->getPanelURI());

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private function loadActiveMFAProviders() {
    $viewer = $this->getViewer();

    $providers = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer)
      ->withStatuses(
        array(
          PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
        ))
      ->execute();

    $providers = mpull($providers, null, 'getPHID');
    $providers = msortv($providers, 'newSortVector');

    return $providers;
  }


}
