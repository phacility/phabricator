<?php

final class PhabricatorApplicationUninstallTransaction
  extends PhabricatorApplicationTransactionType {

  const TRANSACTIONTYPE = 'application.uninstall';

  public function generateOldValue($object) {
    $key = 'phabricator.uninstalled-applications';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $list = $config_entry->getValue();
    $uninstalled = PhabricatorEnv::getEnvConfig($key);

    if (isset($uninstalled[get_class($object)])) {
      return 'uninstalled';
    } else {
      return 'installed';
    }
  }

  public function generateNewValue($object, $value) {
    if ($value === 'uninstall') {
      return 'uninstalled';
    } else {
      return 'installed';
    }
  }

  public function applyExternalEffects($object, $value) {
    $application = $object;
    $user = $this->getActor();

    $key = 'phabricator.uninstalled-applications';
    $config_entry = PhabricatorConfigEntry::loadConfigEntry($key);
    $list = $config_entry->getValue();
    $uninstalled = PhabricatorEnv::getEnvConfig($key);

    if (isset($uninstalled[get_class($application)])) {
      unset($list[get_class($application)]);
    } else {
      $list[get_class($application)] = true;
    }

    $editor = $this->getEditor();
    $content_source = $editor->getContentSource();
    PhabricatorConfigEditor::storeNewValue(
      $user,
      $config_entry,
      $list,
      $content_source);
  }

  public function getTitle() {
    if ($this->getNewValue() === 'uninstalled') {
      return pht(
        '%s uninstalled this application.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s installed this application.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    if ($this->getNewValue() === 'uninstalled') {
      return pht(
        '%s uninstalled %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s installed %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

}
