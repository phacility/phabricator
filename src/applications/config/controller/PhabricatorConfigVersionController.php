<?php

final class PhabricatorConfigVersionController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $title = pht('Version Information');

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $versions = $this->renderModuleStatus($viewer);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('version/');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($versions);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');

  }

  public function renderModuleStatus($viewer) {
    $versions = $this->loadVersions($viewer);

    $version_property_list = id(new PHUIPropertyListView());
    foreach ($versions as $name => $version) {
      $version_property_list->addProperty($name, $version);
    }

    $phabricator_root = dirname(phutil_get_library_root('phabricator'));
    $version_path = $phabricator_root.'/conf/local/VERSION';
    if (Filesystem::pathExists($version_path)) {
      $version_from_file = Filesystem::readFile($version_path);
      $version_property_list->addProperty(
        pht('Local Version'),
        $version_from_file);
    }

    return $version_property_list;
  }

  private function loadVersions(PhabricatorUser $viewer) {
    $specs = array(
      'phabricator',
      'arcanist',
      'phutil',
    );

    $all_libraries = PhutilBootloader::getInstance()->getAllLibraries();
    // This puts the core libraries at the top:
    $other_libraries = array_diff($all_libraries, $specs);
    $specs = array_merge($specs, $other_libraries);

    $futures = array();
    foreach ($specs as $lib) {
      $root = dirname(phutil_get_library_root($lib));
      $futures[$lib] =
        id(new ExecFuture('git log --format=%s -n 1 --', '%H %ct'))
        ->setCWD($root);
    }

    $results = array();
    foreach ($futures as $key => $future) {
      list($err, $stdout) = $future->resolve();
      if (!$err) {
        list($hash, $epoch) = explode(' ', $stdout);
        $version = pht('%s (%s)', $hash, phabricator_date($epoch, $viewer));
      } else {
        $version = pht('Unknown');
      }
      $results[$key] = $version;
    }

    return $results;
  }

}
