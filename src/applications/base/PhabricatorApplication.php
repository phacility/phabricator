<?php

/**
 * @task  info  Application Information
 * @task  ui    UI Integration
 * @task  uri   URI Routing
 * @task  fact  Fact Integration
 * @task  meta  Application Management
 * @group apps
 */
abstract class PhabricatorApplication
  implements PhabricatorPolicyInterface {

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

  /**
   * Return true if this application should not appear in application lists in
   * the UI. Primarily intended for unit test applications or other
   * pseudo-applications.
   *
   * @return bool True to remove application from UI lists.
   */
  public function isUnlisted() {
    return false;
  }

  /**
   * Returns true if an application is first-party (developed by Phacility)
   * and false otherwise.
   *
   * @return bool True if this application is developed by Phacility.
   */
  final public function isFirstParty() {
    $where = id(new ReflectionClass($this))->getFileName();
    $root = phutil_get_library_root('phabricator');

    if (!Filesystem::isDescendant($where, $root)) {
      return false;
    }

    if (Filesystem::isDescendant($where, $root.'/extensions')) {
      return false;
    }

    return true;
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

  public function getApplicationURI($path = '') {
    return $this->getBaseURI().ltrim($path, '/');
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

    if (!$selected) {
      throw new Exception("No application '{$class_name}'!");
    }

    return $selected;
  }

  public static function getAllApplications() {
    static $applications;

    if ($applications === null) {
      $apps = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      // Reorder the applications into "application order". Notably, this
      // ensures their event handlers register in application order.
      $apps = msort($apps, 'getApplicationOrder');
      $apps = mgroup($apps, 'getApplicationGroup');

      $group_order = array_keys(self::getApplicationGroups());
      $apps = array_select_keys($apps, $group_order) + $apps;

      $apps = array_mergev($apps);

      $applications = $apps;
    }

    return $applications;
  }

  public static function getAllInstalledApplications() {
    $all_applications = self::getAllApplications();
    $apps = array();
    foreach ($all_applications as $app) {
      if (!$app->isInstalled()) {
        continue;
      }

      $apps[] = $app;
    }

    return $apps;
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array_merge(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ),
      array_keys($this->getCustomCapabilities()));
  }

  public function getPolicy($capability) {
    $default = $this->getCustomPolicySetting($capability);
    if ($default) {
      return $default;
    }

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if (PhabricatorEnv::getEnvConfig('policy.allow-public')) {
          return PhabricatorPolicies::POLICY_PUBLIC;
        } else {
          return PhabricatorPolicies::POLICY_USER;
        }
      case PhabricatorPolicyCapability::CAN_EDIT:
        return PhabricatorPolicies::POLICY_ADMIN;
      default:
        $spec = $this->getCustomCapabilitySpecification($capability);
        return idx($spec, 'default', PhabricatorPolicies::POLICY_USER);
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  Policies  )----------------------------------------------------------- */

  protected function getCustomCapabilities() {
    return array();
  }

  private function getCustomPolicySetting($capability) {
    if (!$this->isCapabilityEditable($capability)) {
      return null;
    }

    $config = PhabricatorEnv::getEnvConfig('phabricator.application-settings');

    $app = idx($config, $this->getPHID());
    if (!$app) {
      return null;
    }

    $policy = idx($app, 'policy');
    if (!$policy) {
      return null;
    }

    return idx($policy, $capability);
  }


  private function getCustomCapabilitySpecification($capability) {
    $custom = $this->getCustomCapabilities();
    if (empty($custom[$capability])) {
      throw new Exception("Unknown capability '{$capability}'!");
    }
    return $custom[$capability];
  }

  public function getCapabilityLabel($capability) {
    $map = array(
      PhabricatorPolicyCapability::CAN_VIEW => pht('Can Use Application'),
      PhabricatorPolicyCapability::CAN_EDIT => pht('Can Configure Application'),
    );

    $map += ipull($this->getCustomCapabilities(), 'label');

    return idx($map, $capability);
  }

  public function isCapabilityEditable($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->canUninstall();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return false;
      default:
        $spec = $this->getCustomCapabilitySpecification($capability);
        return idx($spec, 'edit', true);
    }
  }

  public function getCapabilityCaption($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if (!$this->canUninstall()) {
          return pht(
            'This application is required for Phabricator to operate, so all '.
            'users must have access to it.');
        } else {
          return null;
        }
      case PhabricatorPolicyCapability::CAN_EDIT:
        return null;
      default:
        $spec = $this->getCustomCapabilitySpecification($capability);
        return idx($spec, 'caption');
    }
  }

}
