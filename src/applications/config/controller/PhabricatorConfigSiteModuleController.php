<?php

final class PhabricatorConfigSiteModuleController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Site Modules'))
      ->appendChild($table);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Site Modules'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('module/sites/');
    $nav->appendChild(
      array(
        $crumbs,
        $box,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => array(pht('Site Modules')),
      ));
  }

}
