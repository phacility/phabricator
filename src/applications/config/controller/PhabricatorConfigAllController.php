<?php

final class PhabricatorConfigAllController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $db_values = id(new PhabricatorConfigEntry())
      ->loadAllWhere('namespace = %s', 'default');
    $db_values = mpull($db_values, null, 'getConfigKey');

    $rows = array();
    $options = PhabricatorApplicationConfigOptions::loadAllOptions();
    ksort($options);
    foreach ($options as $option) {
      $key = $option->getKey();

      if ($option->getMasked()) {
        $value = phutil_tag('em', array(), pht('Masked'));
      } else if ($option->getHidden()) {
        $value = phutil_tag('em', array(), pht('Hidden'));
      } else {
        $value = PhabricatorEnv::getEnvConfig($key);
        $value = PhabricatorConfigJSON::prettyPrintJSON($value);
      }

      $db_value = idx($db_values, $key);
      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => $this->getApplicationURI('edit/'.$key.'/'),
          ),
          $key),
        $value,
        $db_value && !$db_value->getIsDeleted() ? pht('Customized') : '',
      );
    }
    $table = id(new AphrontTableView($rows))
      ->setDeviceReadyTable(true)
      ->setColumnClasses(
        array(
          '',
          'wide',
        ))
      ->setHeaders(
        array(
          pht('Key'),
          pht('Value'),
          pht('Customized'),
        ));

    $title = pht('Current Settings');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($title));

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setNoBackground();

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $future = id(new ExecFuture('git log --format=%%H -n 1 --'))
      ->setCWD($phabricator_root);
    list($err, $stdout) = $future->resolve();
    if (!$err) {
      $display_version = trim($stdout);
    } else {
      $display_version = pht('Unknown');
    }
    $version_property_list = id(new PhabricatorPropertyListView());
    $version_property_list->addProperty(
      pht('Version'),
      $display_version);

    $version_path = $phabricator_root.'/conf/local/VERSION';
    if (Filesystem::pathExists($version_path)) {
      $version_from_file = Filesystem::readFile($version_path);
      $version_property_list->addProperty(
        pht('Local Version'),
        $version_from_file);
    }

    $nav = $this->buildSideNavView();
    $nav->selectFilter('all/');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($version_property_list);
    $nav->appendChild($panel);


    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

}
