<?php

final class PhabricatorConfigVersionsModule
  extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'versions';
  }

  public function getModuleName() {
    return pht('Versions');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();


    $versions = $this->loadVersions();

    $version_property_list = id(new PHUIPropertyListView());
    foreach ($versions as $version) {
      list($name, $hash) = $version;
      $version_property_list->addProperty($name, $hash);
    }

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Current Versions'))
      ->addPropertyList($version_property_list);

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $version_path = $phabricator_root.'/conf/local/VERSION';
    if (Filesystem::pathExists($version_path)) {
      $version_from_file = Filesystem::readFile($version_path);
      $version_property_list->addProperty(
        pht('Local Version'),
        $version_from_file);
    }

    return $object_box;
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
