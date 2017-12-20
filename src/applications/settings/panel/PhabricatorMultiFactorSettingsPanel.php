<?php

final class PhabricatorMultiFactorSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'multifactor';
  }

  public function getPanelName() {
    return pht('Multi-Factor Auth');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsAuthenticationPanelGroup::PANELGROUPKEY;
  }

  public function processRequest(AphrontRequest $request) {
    if ($request->getExists('new')) {
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

    $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());

    $rows = array();
    $rowc = array();

    $highlight_id = $request->getInt('id');
    foreach ($factors as $factor) {

      $impl = $factor->getImplementation();
      if ($impl) {
        $type = $impl->getFactorName();
      } else {
        $type = $factor->getFactorKey();
      }

      if ($factor->getID() == $highlight_id) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }

      $rows[] = array(
        javelin_tag(
          'a',
          array(
            'href' => $this->getPanelURI('?edit='.$factor->getID()),
            'sigil' => 'workflow',
          ),
          $factor->getFactorName()),
        $type,
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
        pht('Name'),
        pht('Type'),
        pht('Created'),
        '',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        '',
        'right',
        'action',
      ));
    $table->setRowClasses($rowc);
    $table->setDeviceVisibility(
      array(
        true,
        false,
        false,
        true,
      ));

    $help_uri = PhabricatorEnv::getDoclink(
      'User Guide: Multi-Factor Authentication');

    $buttons = array();

    $buttons[] = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Add Auth Factor'))
      ->setHref($this->getPanelURI('?new=true'))
      ->setWorkflow(true)
      ->setColor(PHUIButtonView::GREY);

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

    $token = id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $this->getPanelURI());

    $factors = PhabricatorAuthFactor::getAllFactors();

    $form = id(new AphrontFormView())
      ->setUser($viewer);

    $type = $request->getStr('type');
    if (empty($factors[$type]) || !$request->isFormPost()) {
      $factor = null;
    } else {
      $factor = $factors[$type];
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addHiddenInput('new', true);

    if ($factor === null) {
      $choice_control = id(new AphrontFormRadioButtonControl())
        ->setName('type')
        ->setValue(key($factors));

      foreach ($factors as $available_factor) {
        $choice_control->addButton(
          $available_factor->getFactorKey(),
          $available_factor->getFactorName(),
          $available_factor->getFactorDescription());
      }

      $dialog->appendParagraph(
        pht(
          'Adding an additional authentication factor improves the security '.
          'of your account. Choose the type of factor to add:'));

      $form
        ->appendChild($choice_control);

    } else {
      $dialog->addHiddenInput('type', $type);

      $config = $factor->processAddFactorForm(
        $form,
        $request,
        $user);

      if ($config) {
        $config->save();

        $log = PhabricatorUserLog::initializeNewLog(
          $viewer,
          $user->getPHID(),
          PhabricatorUserLog::ACTION_MULTI_ADD);
        $log->save();

        $user->updateMultiFactorEnrollment();

        // Terminate other sessions so they must log in and survive the
        // multi-factor auth check.

        id(new PhabricatorAuthSessionEngine())->terminateLoginSessions(
          $user,
          $request->getCookie(PhabricatorCookies::COOKIE_SESSION));

        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?id='.$config->getID()));
      }
    }

    $dialog
      ->setWidth(AphrontDialogView::WIDTH_FORM)
      ->setTitle(pht('Add Authentication Factor'))
      ->appendChild($form->buildLayoutView())
      ->addSubmitButton(pht('Continue'))
      ->addCancelButton($this->getPanelURI());

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
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
        PhabricatorUserLog::ACTION_MULTI_REMOVE);
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


}
