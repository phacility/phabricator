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

    $versions = $this->loadVersions();

    $version_property_list = id(new PhabricatorPropertyListView());
    foreach ($versions as $version) {
      list($name, $hash) = $version;
      $version_property_list->addProperty($name, $hash);
    }

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
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
      ));
  }

  private function loadVersions() {
    $specs = array(
      array(
        'name' => pht('Phabricator Version'),
        'root' => 'phabricator',
      ),
      array(
        'name' => pht('Arcanist Version'),
        'root' => 'arcanist',
      ),
      array(
        'name' => pht('libphutil Version'),
        'root' => 'phutil',
      ),
    );

    $futures = array();
    foreach ($specs as $key => $spec) {
      $root = dirname(phutil_get_library_root($spec['root']));
      $futures[$key] = id(new ExecFuture('git log --format=%%H -n 1 --'))
        ->setCWD($root);
    }

    $results = array();
    foreach ($futures as $key => $future) {
      list($err, $stdout) = $future->resolve();
      if (!$err) {
        $name = trim($stdout);
      } else {
        $name = pht('Unknown');
      }
      $results[$key] = array($specs[$key]['name'], $name);
    }

    return array_select_keys($results, array_keys($specs));
  }


}
