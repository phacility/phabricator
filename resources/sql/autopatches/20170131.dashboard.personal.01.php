<?php

$table = new PhabricatorDashboard();
$conn = $table->establishConnection('r');
$table_name = 'dashboard_install';

$search_table = new PhabricatorProfileMenuItemConfiguration();
$search_conn = $search_table->establishConnection('w');
$search_table_name = 'search_profilepanelconfiguration';

$viewer = PhabricatorUser::getOmnipotentUser();
$profile_phid = id(new PhabricatorHomeApplication())->getPHID();
$menu_item_key = PhabricatorDashboardProfileMenuItem::MENUITEMKEY;

foreach (new LiskRawMigrationIterator($conn, $table_name) as $install) {

  $dashboard_phid = $install['dashboardPHID'];
  $new_phid = id(new PhabricatorProfileMenuItemConfiguration())->generatePHID();
  $menu_item_properties = json_encode(
    array('dashboardPHID' => $dashboard_phid, 'name' => ''));

  $custom_phid = $install['objectPHID'];
  if ($custom_phid == 'dashboard:default') {
    $custom_phid = null;
  }

  $menu_item_order = 0;

  queryfx(
    $search_conn,
    'INSERT INTO %T (phid, profilePHID, menuItemKey, menuItemProperties, '.
    'visibility, dateCreated, dateModified, menuItemOrder, customPHID) VALUES '.
    '(%s, %s, %s, %s, %s, %d, %d, %d, %ns)',
    $search_table_name,
    $new_phid,
    $profile_phid,
    $menu_item_key,
    $menu_item_properties,
    'visible',
    PhabricatorTime::getNow(),
    PhabricatorTime::getNow(),
    $menu_item_order,
    $custom_phid);

}
