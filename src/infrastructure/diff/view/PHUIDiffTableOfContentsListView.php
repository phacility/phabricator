<?php

final class PHUIDiffTableOfContentsListView extends AphrontView {

  private $items = array();
  private $authorityPackages;
  private $header;
  private $infoView;
  private $background;
  private $bare;

  public function addItem(PHUIDiffTableOfContentsItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function setAuthorityPackages(array $authority_packages) {
    assert_instances_of($authority_packages, 'PhabricatorOwnersPackage');
    $this->authorityPackages = $authority_packages;
    return $this;
  }

  public function getAuthorityPackages() {
    return $this->authorityPackages;
  }

  public function setBackground($background) {
    $this->background = $background;
    return $this;
  }

  public function setHeader(PHUIHeaderView $header) {
    $this->header = $header;
    return $this;
  }

  public function setInfoView(PHUIInfoView $infoview) {
    $this->infoView = $infoview;
    return $this;
  }

  public function setBare($bare) {
    $this->bare = $bare;
    return $this;
  }

  public function getBare() {
    return $this->bare;
  }

  public function render() {
    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-table-of-contents-css');

    Javelin::initBehavior('phabricator-tooltips');

    if ($this->getAuthorityPackages()) {
      $authority = mpull($this->getAuthorityPackages(), null, 'getPHID');
    } else {
      $authority = array();
    }

    $items = $this->items;

    $rows = array();
    $rowc = array();
    foreach ($items as $item) {
      $item->setUser($this->getUser());
      $rows[] = $item->render();

      $have_authority = false;

      $packages = $item->getPackages();
      if ($packages) {
        if (array_intersect_key($packages, $authority)) {
          $have_authority = true;
        }
      }

      if ($have_authority) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = null;
      }
    }

    // Check if any item has content in these columns. If no item does, we'll
    // just hide them.
    $any_coverage = false;
    $any_context = false;
    $any_packages = false;
    foreach ($items as $item) {
      if ($item->getContext() !== null) {
        $any_context = true;
      }

      if (strlen($item->getCoverage())) {
        $any_coverage = true;
      }

      if ($item->getPackages() !== null) {
        $any_packages = true;
      }
    }

    $table = id(new AphrontTableView($rows))
      ->setRowClasses($rowc)
      ->setHeaders(
        array(
          null,
          null,
          null,
          null,
          pht('Path'),
          pht('Coverage (All)'),
          pht('Coverage (Touched)'),
          pht('Packages'),
        ))
      ->setColumnClasses(
        array(
          null,
          'differential-toc-char center',
          'differential-toc-prop center',
          'differential-toc-ftype center',
          'differential-toc-file wide',
          'differential-toc-cov',
          'differential-toc-cov',
          null,
        ))
      ->setColumnVisibility(
        array(
          $any_context,
          true,
          true,
          true,
          true,
          $any_coverage,
          $any_coverage,
          $any_packages,
        ))
      ->setDeviceVisibility(
        array(
          true,
          true,
          true,
          true,
          true,
          false,
          false,
          true,
        ));

    $anchor = id(new PhabricatorAnchorView())
      ->setAnchorName('toc')
      ->setNavigationMarker(true);

    if ($this->bare) {
      return $table;
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Table of Contents'));

    if ($this->header) {
      $header = $this->header;
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground($this->background)
      ->setTable($table)
      ->appendChild($anchor);

    if ($this->infoView) {
      $box->setInfoView($this->infoView);
    }
    return $box;
  }

}
