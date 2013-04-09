<?php

/**
 * @task  info  Application Information
 * @task  ui    UI Integration
 * @task  uri   URI Routing
 * @task  fact  Fact Integration
 * @task  meta  Application Management
 * @group apps
 */
abstract class PhabricatorApplication {

  const GROUP_CORE            = 'core';
  const GROUP_COMMUNICATION   = 'communication';
  const GROUP_ORGANIZATION    = 'organization';
  const GROUP_UTILITIES       = 'util';
  const GROUP_ADMIN           = 'admin';
  const GROUP_DEVELOPER       = 'developer';
  const GROUP_MISC            = 'misc';

  const TILE_INVISIBLE        = 'invisible';
  const TILE_HIDE             = 'hide';
  const TILE_SHOW             = 'show';
  const TILE_FULL             = 'full';

  public static function getApplicationGroups() {
    return array(
      self::GROUP_CORE          => pht('Core Applications'),
      self::GROUP_COMMUNICATION => pht('Communication'),
      self::GROUP_ORGANIZATION  => pht('Organization'),
      self::GROUP_UTILITIES     => pht('Utilities'),
      self::GROUP_ADMIN         => pht('Administration'),
      self::GROUP_DEVELOPER     => pht('Developer Tools'),
      self::GROUP_MISC          => pht('Miscellaneous Applications'),
    );
  }

  public static function getTileDisplayName($constant) {
    $names = array(
      self::TILE_INVISIBLE => pht('Invisible'),
      self::TILE_HIDE => pht('Hidden'),
      self::TILE_SHOW => pht('Show Small Tile'),
      self::TILE_FULL => pht('Show Large Tile'),
    );
    return idx($names, $constant);
  }



/* -(  Application Information  )-------------------------------------------- */


  public function getName() {
    return substr(get_class($this), strlen('PhabricatorApplication'));
  }

  public function getShortDescription() {
    return $this->getName().' Application';
  }

  public function isInstalled() {
    if (!$this->canUninstall()) {
      return true;
    }

    $beta = PhabricatorEnv::getEnvConfig('phabricator.show-beta-applications');
    if (!$beta && $this->isBeta()) {
      return false;
    }

    $uninstalled = PhabricatorEnv::getEnvConfig(
      'phabricator.uninstalled-applications');

    return empty($uninstalled[get_class($this)]);
  }

  public static function isClassInstalled($class) {
    return self::getByClass($class)->isInstalled();
  }

  public function isBeta() {
    return false;
  }

  public function canUninstall() {
    return true;
  }

  public function getPHID() {
    return 'PHID-APPS-'.get_class($this);
  }

  public function getTypeaheadURI() {
    return $this->getBaseURI();
  }

  public function getBaseURI() {
    return null;
  }

  public function getIconURI() {
    return null;
  }

  public function getIconName() {
    return 'application';
  }

  public function shouldAppearInLaunchView() {
    return true;
  }

  public function getApplicationOrder() {
    return PHP_INT_MAX;
  }

  public function getApplicationGroup() {
    return self::GROUP_MISC;
  }

  public function getTitleGlyph() {
    return null;
  }

  public function getHelpURI() {
    // TODO: When these applications get created, link to their docs:
    //
    //  - Drydock
    //  - OAuth Server


    return null;
  }

  public function getEventListeners() {
    return array();
  }

  public function getDefaultTileDisplay(PhabricatorUser $user) {
    switch ($this->getApplicationGroup()) {
      case self::GROUP_CORE:
        return self::TILE_FULL;
      case self::GROUP_UTILITIES:
      case self::GROUP_DEVELOPER:
        return self::TILE_HIDE;
      case self::GROUP_ADMIN:
        if ($user->getIsAdmin()) {
          return self::TILE_SHOW;
        } else {
          return self::TILE_INVISIBLE;
        }
        break;
      default:
        return self::TILE_SHOW;
    }
  }

  public function getRemarkupRules() {
    return array();
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  public function getRoutes() {
    return array();
  }


/* -(  Fact Integration  )--------------------------------------------------- */


  public function getFactObjectsForAnalysis() {
    return array();
  }


/* -(  UI Integration  )----------------------------------------------------- */


  /**
   * Render status elements (like "3 Waiting Reviews") for application list
   * views. These provide a way to alert users to new or pending action items
   * in applications.
   *
   * @param PhabricatorUser Viewing user.
   * @return list<PhabricatorApplicationStatusView> Application status elements.
   * @task ui
   */
  public function loadStatus(PhabricatorUser $user) {
    return array();
  }


  /**
   * You can provide an optional piece of flavor text for the application. This
   * is currently rendered in application launch views if the application has no
   * status elements.
   *
   * @return string|null Flavor text.
   * @task ui
   */
  public function getFlavorText() {
    return null;
  }


  /**
   * Build items for the main menu.
   *
   * @param  PhabricatorUser    The viewing user.
   * @param  AphrontController  The current controller. May be null for special
   *                            pages like 404, exception handlers, etc.
   * @return list<PhabricatorMainMenuIconView> List of menu items.
   * @task ui
   */
  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {
    return array();
  }


  /**
   * On the Phabricator homepage sidebar, this function returns the URL for
   * a quick create X link which is displayed in the wide button only.
   *
   * @return string
   * @task ui
   */
  public function getQuickCreateURI() {
    return null;
  }


/* -(  Application Management  )--------------------------------------------- */

  public static function getByClass($class_name) {
    $selected = null;
    $applications = PhabricatorApplication::getAllApplications();

    foreach ($applications as $application) {
      if (get_class($application) == $class_name) {
        $selected = $application;
        break;
      }
    }
    return $selected;
  }

  public static function getAllApplications() {
    $classes = id(new PhutilSymbolLoader())
            ->setAncestorClass(__CLASS__)
            ->setConcreteOnly(true)
            ->selectAndLoadSymbols();

    $apps = array();

    foreach ($classes as $class) {
      $app = newv($class['name'], array());
      $apps[] = $app;
    }

    // Reorder the applications into "application order". Notably, this ensures
    // their event handlers register in application order.
    $apps = msort($apps, 'getApplicationOrder');
    $apps = mgroup($apps, 'getApplicationGroup');
    $apps = array_select_keys($apps, self::getApplicationGroups()) + $apps;
    $apps = array_mergev($apps);

    return $apps;
  }

  public static function getAllInstalledApplications() {
    static $applications;

    if (empty($applications)) {
      $all_applications = self::getAllApplications();
      $apps = array();
      foreach ($all_applications as $app) {
        if (!$app->isInstalled()) {
          continue;
        }

        $apps[] = $app;
      }

      $applications = $apps;
    }

    return $applications;
  }

}

