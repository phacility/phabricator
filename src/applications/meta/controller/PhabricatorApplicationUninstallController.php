<?php

final class PhabricatorApplicationUninstallController
  extends PhabricatorApplicationsController {

  private $application;
  private $action;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $this->action = $request->getURIData('action');
    $this->application = $request->getURIData('application');

    $selected = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($this->application))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$selected) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('view/'.$this->application);

    $prototypes_enabled = PhabricatorEnv::getEnvConfig(
      'phabricator.show-prototypes');

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addCancelButton($view_uri);

    if ($selected->isPrototype() && !$prototypes_enabled) {
      $dialog
        ->setTitle(pht('Prototypes Not Enabled'))
        ->appendChild(
          pht(
            'To manage prototypes, enable them by setting %s in your '.
            'Phabricator configuration.',
            phutil_tag('tt', array(), 'phabricator.show-prototypes')));
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($request->isDialogFormPost()) {
      $this->manageApplication();
      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    if ($this->action == 'install') {
      if ($selected->canUninstall()) {
        $dialog
          ->setTitle(pht('Confirmation'))
          ->appendChild(
            pht(
              'Install %s application?',
              $selected->getName()))
          ->addSubmitButton(pht('Install'));

      } else {
        $dialog
          ->setTitle(pht('Information'))
          ->appendChild(pht('You cannot install an installed application.'));
      }
    } else {
      if ($selected->canUninstall()) {
        $dialog->setTitle(pht('Really Uninstall Application?'));

        if ($selected instanceof PhabricatorHomeApplication) {
          $dialog
            ->appendParagraph(
              pht(
                'Are you absolutely certain you want to uninstall the Home '.
                'application?'))
            ->appendParagraph(
              pht(
                'This is very unusual and will leave you without any '.
                'content on the Phabricator home page. You should only '.
                'do this if you are certain you know what you are doing.'))
            ->addSubmitButton(pht('Completely Break Phabricator'));
        } else {
          $dialog
            ->appendParagraph(
              pht(
                'Really uninstall the %s application?',
                $selected->getName()))
            ->addSubmitButton(pht('Uninstall'));
        }
      } else {
        $dialog
          ->setTitle(pht('Information'))
          ->appendChild(
            pht(
              'This application cannot be uninstalled, '.
              'because it is required for Phabricator to work.'));
      }
    }
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  public function manageApplication() {
    $key = 'phabricator.uninstalled-applications';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $list = $config_entry->getValue();
    $uninstalled = PhabricatorEnv::getEnvConfig($key);

    if (isset($uninstalled[$this->application])) {
      unset($list[$this->application]);
    } else {
      $list[$this->application] = true;
    }

    PhabricatorConfigEditor::storeNewValue(
      $this->getViewer(),
      $config_entry,
      $list,
      PhabricatorContentSource::newFromRequest($this->getRequest()));
  }

}
