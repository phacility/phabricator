<?php

/**
 * An install of a dashboard. Examples might be
 * - the home page for a user
 * - the profile page for a user
 * - the profile page for a project
 */
final class PhabricatorDashboardInstall
  extends PhabricatorDashboardDAO {

  protected $installerPHID;
  protected $objectPHID;
  protected $applicationClass;
  protected $dashboardPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'applicationClass' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'objectPHID' => array(
          'columns' => array('objectPHID', 'applicationClass'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function getDashboard(
    PhabricatorUser $viewer,
    $object_phid,
    $application_class) {

    $dashboard = null;
    $dashboard_install = id(new PhabricatorDashboardInstall())
      ->loadOneWhere(
        'objectPHID = %s AND applicationClass = %s',
        $object_phid,
        $application_class);
    if ($dashboard_install) {
      $dashboard = id(new PhabricatorDashboardQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($dashboard_install->getDashboardPHID()))
        ->needPanels(true)
        ->executeOne();
    }

    return $dashboard;
  }
}
