<?php

abstract class DifferentialController extends PhabricatorController {

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new DifferentialRevisionSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  protected function buildTableOfContents(
    array $changesets,
    array $visible_changesets,
    array $coverage) {
    $viewer = $this->getViewer();

    $toc_view = id(new PHUIDiffTableOfContentsListView())
      ->setUser($viewer);

    foreach ($changesets as $changeset_id => $changeset) {
      $is_visible = isset($visible_changesets[$changeset_id]);
      $anchor = $changeset->getAnchorName();

      $filename = $changeset->getFilename();
      $coverage_id = 'differential-mcoverage-'.md5($filename);

      $item = id(new PHUIDiffTableOfContentsItemView())
        ->setChangeset($changeset)
        ->setIsVisible($is_visible)
        ->setAnchor($anchor)
        ->setCoverage(idx($coverage, $filename))
        ->setCoverageID($coverage_id);

      $toc_view->addItem($item);
    }

    return $toc_view;
  }

}
