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
