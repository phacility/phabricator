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

    if (!$selected || !$selected->canUninstall()) {
      return new Aphront404Response();
    }

    if ($request->isDialogFormPost()) {
      $this->manageApplication();
     return id(new AphrontRedirectResponse())->setURI('/applications/');
    }

    if ($this->action == 'install') {

      $dialog = id(new AphrontDialogView())
             ->setUser($user)
             ->setTitle('Confirmation')
             ->appendChild(
               'Install '. $selected->getName(). ' application ?'
               )
             ->addSubmitButton('Install')
             ->addCancelButton('/applications/view/'.$this->application);
    } else {
      $dialog = id(new AphrontDialogView())
             ->setUser($user)
             ->setTitle('Confirmation')
             ->appendChild(
               'Really Uninstall '. $selected->getName(). ' application ?'
               )
             ->addSubmitButton('Uninstall')
             ->addCancelButton('/applications/view/'.$this->application);
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

