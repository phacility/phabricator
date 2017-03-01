<?php

final class PhabricatorApplicationProfileMenuItem
  extends PhabricatorProfileMenuItem {

  const MENUITEMKEY = 'application';

  const FIELD_APPLICATION = 'application';

  public function getMenuItemTypeIcon() {
    return 'fa-globe';
  }

  public function getMenuItemTypeName() {
    return pht('Application');
  }

  public function canAddToObject($object) {
    return true;
  }

  public function getDisplayName(
    PhabricatorProfileMenuItemConfiguration $config) {
    $application = $this->getApplication($config);
    if (!$application) {
      return pht('(Restricted/Invalid Application)');
    }

    $name = $this->getName($config);
    if (strlen($name)) {
      return $name;
    }

    return $application->getName();
  }

  public function buildEditEngineFields(
    PhabricatorProfileMenuItemConfiguration $config) {
    return array(
      id(new PhabricatorDatasourceEditField())
        ->setKey(self::FIELD_APPLICATION)
        ->setLabel(pht('Application'))
        ->setDatasource(new PhabricatorApplicationDatasource())
        ->setIsRequired(true)
        ->setSingleValue($config->getMenuItemProperty('application')),
      id(new PhabricatorTextEditField())
        ->setKey('name')
        ->setLabel(pht('Name'))
        ->setValue($this->getName($config)),
    );
  }

  private function getName(
    PhabricatorProfileMenuItemConfiguration $config) {
    return $config->getMenuItemProperty('name');
  }

  private function getApplication(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $phid = $config->getMenuItemProperty('application');

    $apps = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->execute();

    return head($apps);
  }

  protected function newNavigationMenuItems(
    PhabricatorProfileMenuItemConfiguration $config) {
    $viewer = $this->getViewer();
    $app = $this->getApplication($config);
    if (!$app) {
      return array();
    }

    $is_installed = PhabricatorApplication::isClassInstalledForViewer(
      get_class($app),
      $viewer);
    if (!$is_installed) {
      return array();
    }

    $item = $this->newItem()
      ->setHref($app->getApplicationURI())
      ->setName($this->getDisplayName($config))
      ->setIcon($app->getIcon());

    // Don't show tooltip if they've set a custom name
    $name = $config->getMenuItemProperty('name');
    if (!strlen($name)) {
      $item->setTooltip($app->getShortDescription());
    }

    return array(
      $item,
    );
  }

  public function validateTransactions(
    PhabricatorProfileMenuItemConfiguration $config,
    $field_key,
    $value,
    array $xactions) {

    $viewer = $this->getViewer();
    $errors = array();

    if ($field_key == self::FIELD_APPLICATION) {
      if ($this->isEmptyTransaction($value, $xactions)) {
       $errors[] = $this->newRequiredError(
         pht('You must choose an application.'),
         $field_key);
      }

      foreach ($xactions as $xaction) {
        $new = $xaction['new'];

        if (!$new) {
          continue;
        }

        if ($new === $value) {
          continue;
        }

        $applications = id(new PhabricatorApplicationQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($new))
          ->execute();
        if (!$applications) {
          $errors[] = $this->newInvalidError(
            pht(
              'Application "%s" is not a valid application which you have '.
              'permission to see.',
              $new),
            $xaction['xaction']);
        }
      }
    }

    return $errors;
  }

}
