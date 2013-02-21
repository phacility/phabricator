<?php

final class PhabricatorApplicationUninstallController
  extends PhabricatorApplicationsController {

  private $application;
  private $action;

  public function willProcessRequest(array $data) {
    $this->application = $data['application'];
    $this->action = $data['action'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $selected = PhabricatorApplication::getByClass($this->application);

    if (!$selected) {
      return new Aphront404Response();
    }

    $view_uri = $this->getApplicationURI('view/'.$this->application);

    if ($request->isDialogFormPost()) {
      $this->manageApplication();
      return id(new AphrontRedirectResponse())->setURI($view_uri);
    }

    $dialog = id(new AphrontDialogView())
               ->setUser($user)
               ->addCancelButton($view_uri);

    if ($this->action == 'install') {
      if ($selected->canUninstall()) {
        $dialog->setTitle('Confirmation')
               ->appendChild(
                 'Install '. $selected->getName(). ' application ?')
               ->addSubmitButton('Install');

      } else {
        $dialog->setTitle('Information')
               ->appendChild('You cannot install a installed application.');
      }
    } else {
      if ($selected->canUninstall()) {
        $dialog->setTitle('Confirmation')
               ->appendChild(
                 'Really Uninstall '. $selected->getName(). ' application ?')
               ->addSubmitButton('Uninstall');
      } else {
        $dialog->setTitle('Information')
               ->appendChild(
                 'This application cannot be uninstalled,
                 because it is required for Phabricator to work.');
      }
    }
    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

  public function manageApplication() {
    $key = 'phabricator.uninstalled-applications';

    $config_entry = id(new PhabricatorConfigEntry())
                 ->loadOneWhere(
                   'configKey = %s AND namespace = %s',
                    $key,
                   'default');

    if (!$config_entry) {
      $config_entry = id(new PhabricatorConfigEntry())
                   ->setConfigKey($key)
                   ->setNamespace('default');
  }

  $list = $config_entry->getValue();

  $uninstalled = PhabricatorEnv::getEnvConfig($key);

  if ($uninstalled[$this->application]) {
    unset($list[$this->application]);
  } else {
      $list[$this->application] = true;
  }

  $xaction = id(new PhabricatorConfigTransaction())
            ->setTransactionType(PhabricatorConfigTransaction::TYPE_EDIT)
            ->setNewValue(
              array(
                 'deleted' => false,
                 'value' => $list
              ));

  $editor = id(new PhabricatorConfigEditor())
         ->setActor($this->getRequest()->getUser())
         ->setContinueOnNoEffect(true)
         ->setContentSource(
           PhabricatorContentSource::newForSource(
             PhabricatorContentSource::SOURCE_WEB,
             array(
               'ip' => $this->getRequest()->getRemoteAddr(),
             )));


  $editor->applyTransactions($config_entry, array($xaction));

  }

}

