<?php

abstract class PhabricatorConfigController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView($filter = null, $for_app = false) {


    $guide_href = new PhutilURI('/guides/');
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addFilter('/',
      pht('Core Settings'), null, 'fa-gear');
    $nav->addFilter('application/',
      pht('Application Settings'), null, 'fa-globe');
    $nav->addFilter('history/',
      pht('Settings History'), null, 'fa-history');
    $nav->addFilter('version/',
      pht('Version Information'), null, 'fa-download');
    $nav->addFilter('all/',
      pht('All Settings'), null, 'fa-list-ul');
    $nav->addLabel(pht('Setup'));
    $nav->addFilter('issue/',
      pht('Setup Issues'), null, 'fa-warning');
    $nav->addFilter(null,
      pht('Installation Guide'), $guide_href, 'fa-book');
    $nav->addLabel(pht('Database'));
    $nav->addFilter('database/',
      pht('Database Status'), null, 'fa-heartbeat');
    $nav->addFilter('dbissue/',
      pht('Database Issues'), null, 'fa-exclamation-circle');
    $nav->addLabel(pht('Cache'));
    $nav->addFilter('cache/',
      pht('Cache Status'), null, 'fa-home');
    $nav->addLabel(pht('Cluster'));
    $nav->addFilter('cluster/databases/',
      pht('Database Servers'), null, 'fa-database');
    $nav->addFilter('cluster/notifications/',
      pht('Notification Servers'), null, 'fa-bell-o');
    $nav->addFilter('cluster/repositories/',
      pht('Repository Servers'), null, 'fa-code');
    $nav->addFilter('cluster/search/',
      pht('Search Servers'), null, 'fa-search');
    $nav->addLabel(pht('Modules'));

    $modules = PhabricatorConfigModule::getAllModules();
    foreach ($modules as $key => $module) {
      $nav->addFilter('module/'.$key.'/',
        $module->getModuleName(), null, 'fa-puzzle-piece');
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

  public function buildHeaderView($text, $action = null) {
    $viewer = $this->getViewer();

    $file = PhabricatorFile::loadBuiltin($viewer, 'projects/v3/manage.png');
    $image = $file->getBestURI($file);
    $header = id(new PHUIHeaderView())
      ->setHeader($text)
      ->setProfileHeader(true)
      ->setImage($image);

    if ($action) {
      $header->addActionLink($action);
    }

    return $header;
  }

  public function buildConfigBoxView($title, $content, $action = null) {
    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    if ($action) {
      $header->addActionItem($action);
    }

    $view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($content)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG);

    return $view;
  }

}
