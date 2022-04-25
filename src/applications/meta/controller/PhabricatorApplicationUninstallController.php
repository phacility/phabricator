<?php

final class PhabricatorApplicationUninstallController
  extends PhabricatorApplicationsController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $user = $request->getUser();
    $action = $request->getURIData('action');
    $application_name = $request->getURIData('application');

    $application = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withClasses(array($application_name))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$application) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('view/'.$application_name);

    $prototypes_enabled = PhabricatorEnv::getEnvConfig(
      'phabricator.show-prototypes');

    $dialog = id(new AphrontDialogView())
      ->setUser($viewer)
      ->addCancelButton($view_uri);

    if ($application->isPrototype() && !$prototypes_enabled) {
      $dialog
        ->setTitle(pht('Prototypes Not Enabled'))
        ->appendChild(
          pht(
            'To manage prototypes, enable them by setting %s in your '.
            'configuration.',
            phutil_tag('tt', array(), 'phabricator.show-prototypes')));
      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    if ($request->isDialogFormPost()) {
      $xactions = array();
      $template = $application->getApplicationTransactionTemplate();
      $xactions[] = id(clone $template)
        ->setTransactionType(
            PhabricatorApplicationUninstallTransaction::TRANSACTIONTYPE)
        ->setNewValue($action);

      $editor = id(new PhabricatorApplicationEditor())
        ->setActor($user)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true);

      try {
        $editor->applyTransactions($application, $xactions);
        return id(new AphrontRedirectResponse())->setURI($view_uri);
      } catch (PhabricatorApplicationTransactionValidationException $ex) {
        $validation_exception = $ex;
      }

      return $this->newDialog()
        ->setTitle(pht('Validation Failed'))
        ->setValidationException($validation_exception)
        ->addCancelButton($view_uri);
    }

    if ($action == 'install') {
      if ($application->canUninstall()) {
        $dialog
          ->setTitle(pht('Confirmation'))
          ->appendChild(
            pht(
              'Install %s application?',
              $application->getName()))
          ->addSubmitButton(pht('Install'));

      } else {
        $dialog
          ->setTitle(pht('Information'))
          ->appendChild(pht('You cannot install an installed application.'));
      }
    } else {
      if ($application->canUninstall()) {
        $dialog->setTitle(pht('Really Uninstall Application?'));

        if ($application instanceof PhabricatorHomeApplication) {
          $dialog
            ->appendParagraph(
              pht(
                'Are you absolutely certain you want to uninstall the Home '.
                'application?'))
            ->appendParagraph(
              pht(
                'This is very unusual and will leave you without any '.
                'content on the home page. You should only do this if you '.
                'are certain you know what you are doing.'))
            ->addSubmitButton(pht('Completely Break Everything'));
        } else {
          $dialog
            ->appendParagraph(
              pht(
                'Really uninstall the %s application?',
                $application->getName()))
            ->addSubmitButton(pht('Uninstall'));
        }
      } else {
        $dialog
          ->setTitle(pht('Information'))
          ->appendChild(
            pht(
              'This application is required and cannot be uninstalled.'));
      }
    }
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
