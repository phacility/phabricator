<?php

/**
 * @task  info  Application Information
 * @task  ui    UI Integration
 * @task  uri   URI Routing
 * @task  mail  Email integration
 * @task  fact  Fact Integration
 * @task  meta  Application Management
 */
abstract class PhabricatorApplication
  extends Phobject
  implements PhabricatorPolicyInterface {

  const MAX_STATUS_ITEMS      = 100;

  const GROUP_CORE            = 'core';
  const GROUP_UTILITIES       = 'util';
  const GROUP_ADMIN           = 'admin';
  const GROUP_DEVELOPER       = 'developer';

  final public static function getApplicationGroups() {
    return array(
      self::GROUP_CORE          => pht('Core Applications'),
      self::GROUP_UTILITIES     => pht('Utilities'),
      self::GROUP_ADMIN         => pht('Administration'),
      self::GROUP_DEVELOPER     => pht('Developer Tools'),
    );
  }


/* -(  Application Information  )-------------------------------------------- */

  abstract public function getName();

  public function getShortDescription() {
    return pht('%s Application', $this->getName());
  }

  final public function isInstalled() {
    if (!$this->canUninstall()) {
      return true;
    }

    $prototypes = PhabricatorEnv::getEnvConfig('phabricator.show-prototypes');
    if (!$prototypes && $this->isPrototype()) {
      return false;
    }

    $uninstalled = PhabricatorEnv::getEnvConfig(
      'phabricator.uninstalled-applications');

    return empty($uninstalled[get_class($this)]);
  }


  public function isPrototype() {
    return false;
  }


  /**
   * Return `true` if this application should never appear in application lists
   * in the UI. Primarily intended for unit test applications or other
   * pseudo-applications.
   *
   * Few applications should be unlisted. For most applications, use
   * @{method:isLaunchable} to hide them from main launch views instead.
   *
   * @return bool True to remove application from UI lists.
   */
  public function isUnlisted() {
    return false;
  }


  /**
   * Return `true` if this application is a normal application with a base
   * URI and a web interface.
   *
   * Launchable applications can be pinned to the home page, and show up in the
   * "Launcher" view of the Applications application. Making an application
   * unlauncahble prevents pinning and hides it from this view.
   *
   * Usually, an application should be marked unlaunchable if:
   *
   *   - it is available on every page anyway (like search); or
   *   - it does not have a web interface (like subscriptions); or
   *   - it is still pre-release and being intentionally buried.
   *
   * To hide applications more completely, use @{method:isUnlisted}.
   *
   * @return bool True if the application is launchable.
   */
  public function isLaunchable() {
    return true;
  }


  /**
   * Return `true` if this application should be pinned by default.
   *
   * Users who have not yet set preferences see a default list of applications.
   *
   * @param PhabricatorUser User viewing the pinned application list.
   * @return bool True if this application should be pinned by default.
   */
  public function isPinnedByDefault(PhabricatorUser $viewer) {
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

  final public function getPHID() {
    return 'PHID-APPS-'.get_class($this);
  }

  public function getTypeaheadURI() {
    return $this->isLaunchable() ? $this->getBaseURI() : null;
  }

  public function getBaseURI() {
    return null;
  }

  final public function getApplicationURI($path = '') {
    return $this->getBaseURI().ltrim($path, '/');
  }

  public function getIconURI() {
    return null;
  }

  public function getFontIcon() {
    return 'fa-puzzle-piece';
  }

  public function getApplicationOrder() {
    return PHP_INT_MAX;
  }

  public function getApplicationGroup() {
    return self::GROUP_CORE;
  }

  public function getTitleGlyph() {
    return null;
  }

  final public function getHelpMenuItems(PhabricatorUser $viewer) {
    $items = array();

    $articles = $this->getHelpDocumentationArticles($viewer);
    if ($articles) {
      $items[] = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LABEL)
        ->setName(pht('%s Documentation', $this->getName()));
      foreach ($articles as $article) {
        $item = id(new PHUIListItemView())
          ->setName($article['name'])
          ->setIcon('fa-book')
          ->setHref($article['href']);

        $items[] = $item;
      }
    }

    $command_specs = $this->getMailCommandObjects();
    if ($command_specs) {
      $items[] = id(new PHUIListItemView())
        ->setType(PHUIListItemView::TYPE_LABEL)
        ->setName(pht('Email Help'));
      foreach ($command_specs as $key => $spec) {
        $object = $spec['object'];

        $class = get_class($this);
        $href = '/applications/mailcommands/'.$class.'/'.$key.'/';

        $item = id(new PHUIListItemView())
          ->setName($spec['name'])
          ->setIcon('fa-envelope-o')
          ->setHref($href);
        $items[] = $item;
      }
    }

    return $items;
  }

  public function getHelpDocumentationArticles(PhabricatorUser $viewer) {
    return array();
  }

  public function getOverview() {
    return null;
  }

  public function getEventListeners() {
    return array();
  }

  public function getRemarkupRules() {
    return array();
  }

  public function getQuicksandURIPatternBlacklist() {
    return array();
  }

  public function getMailCommandObjects() {
    return array();
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  public function getRoutes() {
    return array();
  }


/* -(  Email Integration  )-------------------------------------------------- */


  public function supportsEmailIntegration() {
    return false;
  }

  final protected function getInboundEmailSupportLink() {
    return PhabricatorEnv::getDocLink('Configuring Inbound Email');
  }

  public function getAppEmailBlurb() {
    throw new PhutilMethodNotImplementedException();
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
   * @return string
   * @task ui
   */
  final public static function formatStatusCount(
    $count,
    $limit_string = '%s',
    $base_string = '%d') {
    if ($count == self::MAX_STATUS_ITEMS) {
      $count_str = pht($limit_string, ($count - 1).'+');
    } else {
      $count_str = pht($base_string, $count);
    }
    return $count_str;
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
   * @return list<PHUIListItemView> List of menu items.
   * @task ui
   */
  public function buildMainMenuItems(
    PhabricatorUser $user,
    PhabricatorController $controller = null) {
    return array();
  }


  /**
   * Build extra items for the main menu. Generally, this is used to render
   * static dropdowns.
   *
   * @param  PhabricatorUser    The viewing user.
   * @param  AphrontController  The current controller. May be null for special
   *                            pages like 404, exception handlers, etc.
   * @return view               List of menu items.
   * @task ui
   */
  public function buildMainMenuExtraNodes(
    PhabricatorUser $viewer,
    PhabricatorController $controller = null) {
    return array();
  }


  /**
   * Build items for the "quick create" menu.
   *
   * @param   PhabricatorUser         The viewing user.
   * @return  list<PHUIListItemView>  List of menu items.
   */
  public function getQuickCreateItems(PhabricatorUser $viewer) {
    return array();
  }


/* -(  Application Management  )--------------------------------------------- */


  final public static function getByClass($class_name) {
    $selected = null;
    $applications = self::getAllApplications();

    foreach ($applications as $application) {
      if (get_class($application) == $class_name) {
        $selected = $application;
        break;
      }
    }

    if (!$selected) {
      throw new Exception(pht("No application '%s'!", $class_name));
    }

    return $selected;
  }

  final public static function getAllApplications() {
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

  final public static function getAllInstalledApplications() {
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


  /**
   * Determine if an application is installed, by application class name.
   *
   * To check if an application is installed //and// available to a particular
   * viewer, user @{method:isClassInstalledForViewer}.
   *
   * @param string  Application class name.
   * @return bool   True if the class is installed.
   * @task meta
   */
  final public static function isClassInstalled($class) {
    return self::getByClass($class)->isInstalled();
  }


  /**
   * Determine if an application is installed and available to a viewer, by
   * application class name.
   *
   * To check if an application is installed at all, use
   * @{method:isClassInstalled}.
   *
   * @param string Application class name.
   * @param PhabricatorUser Viewing user.
   * @return bool True if the class is installed for the viewer.
   * @task meta
   */
  final public static function isClassInstalledForViewer(
    $class,
    PhabricatorUser $viewer) {

    $cache = PhabricatorCaches::getRequestCache();
    $viewer_phid = $viewer->getPHID();
    $key = 'app.'.$class.'.installed.'.$viewer_phid;

    $result = $cache->getKey($key);
    if ($result === null) {
      if (!self::isClassInstalled($class)) {
        $result = false;
      } else {
        $result = PhabricatorPolicyFilter::hasCapability(
          $viewer,
          self::getByClass($class),
          PhabricatorPolicyCapability::CAN_VIEW);
      }

      $cache->setKey($key, $result);
    }

    return $result;
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
        return PhabricatorPolicies::getMostOpenPolicy();
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

  final private function getCustomPolicySetting($capability) {
    if (!$this->isCapabilityEditable($capability)) {
      return null;
    }

    $policy_locked = PhabricatorEnv::getEnvConfig('policy.locked');
    if (isset($policy_locked[$capability])) {
      return $policy_locked[$capability];
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


  final private function getCustomCapabilitySpecification($capability) {
    $custom = $this->getCustomCapabilities();
    if (!isset($custom[$capability])) {
      throw new Exception(pht("Unknown capability '%s'!", $capability));
    }
    return $custom[$capability];
  }

  final public function getCapabilityLabel($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Can Use Application');
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht('Can Configure Application');
    }

    $capobj = PhabricatorPolicyCapability::getCapabilityByKey($capability);
    if ($capobj) {
      return $capobj->getCapabilityName();
    }

    return null;
  }

  final public function isCapabilityEditable($capability) {
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

  final public function getCapabilityCaption($capability) {
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

  final public function getCapabilityTemplatePHIDType($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
      case PhabricatorPolicyCapability::CAN_EDIT:
        return null;
    }

    $spec = $this->getCustomCapabilitySpecification($capability);
    return idx($spec, 'template');
  }

  public function getApplicationSearchDocumentTypes() {
    return array();
  }

}
