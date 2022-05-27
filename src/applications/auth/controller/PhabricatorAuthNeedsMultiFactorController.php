<?php

final class PhabricatorAuthNeedsMultiFactorController
  extends PhabricatorAuthController {

  public function shouldRequireMultiFactorEnrollment() {
    // Users need access to this controller in order to enroll in multi-factor
    // auth.
    return false;
  }

  public function shouldRequireEnabledUser() {
    // Users who haven't been approved yet are allowed to enroll in MFA. We'll
    // kick disabled users out later.
    return false;
  }

  public function shouldRequireEmailVerification() {
    // Users who haven't verified their email addresses yet can still enroll
    // in MFA.
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    if ($viewer->getIsDisabled()) {
      // We allowed unapproved and disabled users to hit this controller, but
      // want to kick out disabled users now.
      return new Aphront400Response();
    }

    $panels = $this->loadPanels();

    $multifactor_key = id(new PhabricatorMultiFactorSettingsPanel())
      ->getPanelKey();

    $panel_key = $request->getURIData('pageKey');
    if (!strlen($panel_key)) {
      $panel_key = $multifactor_key;
    }

    if (!isset($panels[$panel_key])) {
      return new Aphront404Response();
    }

    $nav = $this->newNavigation();
    $nav->selectFilter($panel_key);

    $panel = $panels[$panel_key];

    $viewer->updateMultiFactorEnrollment();

    if ($panel_key === $multifactor_key) {
      $header_text = pht('Add Multi-Factor Auth');
      $help = $this->newGuidance();
      $panel->setIsEnrollment(true);
    } else {
      $header_text = $panel->getPanelName();
      $help = null;
    }

    $response = $panel
      ->setController($this)
      ->setNavigation($nav)
      ->processRequest($request);

    if (($response instanceof AphrontResponse) ||
        ($response instanceof AphrontResponseProducerInterface)) {
      return $response;
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Add Multi-Factor Auth'))
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(
        array(
          $help,
          $response,
        ));

    return $this->newPage()
      ->setTitle(pht('Add Multi-Factor Authentication'))
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);

  }

  private function loadPanels() {
    $viewer = $this->getViewer();
    $preferences = PhabricatorUserPreferences::loadUserPreferences($viewer);

    $panels = PhabricatorSettingsPanel::getAllDisplayPanels();
    $base_uri = $this->newEnrollBaseURI();

    $result = array();
    foreach ($panels as $key => $panel) {
      $panel
        ->setPreferences($preferences)
        ->setViewer($viewer)
        ->setUser($viewer)
        ->setOverrideURI(urisprintf('%s%s/', $base_uri, $key));

      if (!$panel->isEnabled()) {
        continue;
      }

      if (!$panel->isUserPanel()) {
        continue;
      }

      if (!$panel->isMultiFactorEnrollmentPanel()) {
        continue;
      }

      if (!empty($result[$key])) {
        throw new Exception(pht(
          "Two settings panels share the same panel key ('%s'): %s, %s.",
          $key,
          get_class($panel),
          get_class($result[$key])));
      }

      $result[$key] = $panel;
    }

    return $result;
  }


  private function newNavigation() {
    $viewer = $this->getViewer();

    $enroll_uri = $this->newEnrollBaseURI();

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($enroll_uri));

    $multifactor_key = id(new PhabricatorMultiFactorSettingsPanel())
      ->getPanelKey();

    $nav->addFilter(
      $multifactor_key,
      pht('Enroll in MFA'),
      null,
      'fa-exclamation-triangle blue');

    $panels = $this->loadPanels();

    if ($panels) {
      $nav->addLabel(pht('Settings'));
    }

    foreach ($panels as $panel_key => $panel) {
      if ($panel_key === $multifactor_key) {
        continue;
      }

      $nav->addFilter(
        $panel->getPanelKey(),
        $panel->getPanelName(),
        null,
        $panel->getPanelMenuIcon());
    }

    return $nav;
  }

  private function newEnrollBaseURI() {
    return $this->getApplicationURI('enroll/');
  }

  private function newGuidance() {
    $viewer = $this->getViewer();

    if ($viewer->getIsEnrolledInMultiFactor()) {
      $guidance = pht(
        '{icon check, color="green"} **Setup Complete!**'.
        "\n\n".
        'You have successfully configured multi-factor authentication '.
        'for your account.'.
        "\n\n".
        'You can make adjustments from the [[ /settings/ | Settings ]] panel '.
        'later.');

      return $this->newDialog()
        ->setTitle(pht('Multi-Factor Authentication Setup Complete'))
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->appendChild(new PHUIRemarkupView($viewer, $guidance))
        ->addCancelButton('/', pht('Continue'));
    }

    $views = array();

    $messages = array();

    $messages[] = pht(
      'Before you can use this software, you need to add multi-factor '.
      'authentication to your account. Multi-factor authentication helps '.
      'secure your account by making it more difficult for attackers to '.
      'gain access or take sensitive actions.');

    $view = id(new PHUIInfoView())
      ->setTitle(pht('Add Multi-Factor Authentication To Your Account'))
      ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
      ->setErrors($messages);

    $views[] = $view;


    $providers = id(new PhabricatorAuthFactorProviderQuery())
      ->setViewer($viewer)
      ->withStatuses(
        array(
          PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
        ))
      ->execute();
    if (!$providers) {
      $messages = array();

      $required_key = 'security.require-multi-factor-auth';

      $messages[] = pht(
        'This install has the configuration option "%s" enabled, but does '.
        'not have any active multifactor providers configured. This means '.
        'you are required to add MFA, but are also prevented from doing so. '.
        'An administrator must disable "%s" or enable an MFA provider to '.
        'allow you to continue.',
        $required_key,
        $required_key);

      $view = id(new PHUIInfoView())
        ->setTitle(pht('Multi-Factor Authentication is Misconfigured'))
        ->setSeverity(PHUIInfoView::SEVERITY_ERROR)
        ->setErrors($messages);

      $views[] = $view;
    }

    return $views;
  }

}
