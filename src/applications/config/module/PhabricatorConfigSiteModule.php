<?php

final class PhabricatorConfigSiteModule extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'site';
  }

  public function getModuleName() {
    return pht('Sites');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $sites = AphrontSite::getAllSites();

    $rows = array();
    foreach ($sites as $key => $site) {
      $rows[] = array(
        $site->getPriority(),
        $key,
        $site->getDescription(),
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Priority'),
          pht('Class'),
          pht('Description'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri',
          'wide',
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Sites'))
      ->setTable($table);
  }

}
