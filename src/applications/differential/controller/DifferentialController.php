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

    $have_owners = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorOwnersApplication',
      $viewer);
    if ($have_owners) {
      $repository_phid = null;
      if ($changesets) {
        $changeset = head($changesets);
        $diff = $changeset->getDiff();
        $repository_phid = $diff->getRepositoryPHID();
      }

      if (!$repository_phid) {
        $have_owners = false;
      } else {
        if ($viewer->getPHID()) {
          $packages = id(new PhabricatorOwnersPackageQuery())
            ->setViewer($viewer)
            ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
            ->withAuthorityPHIDs(array($viewer->getPHID()))
            ->execute();
          $toc_view->setAuthorityPackages($packages);
        }

        // TODO: For Subversion, we should adjust these paths to be relative to
        // the repository root where possible.
        $paths = mpull($changesets, 'getFilename');

        $control_query = id(new PhabricatorOwnersPackageQuery())
          ->setViewer($viewer)
          ->withStatuses(array(PhabricatorOwnersPackage::STATUS_ACTIVE))
          ->withControl($repository_phid, $paths);
        $control_query->execute();
      }
    }

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

      if ($have_owners) {
        $packages = $control_query->getControllingPackagesForPath(
          $repository_phid,
          $changeset->getFilename());
        $item->setPackages($packages);
      }

      $toc_view->addItem($item);
    }

    return $toc_view;
  }

}
