<?php

final class PHUIDiffTableOfContentsListView extends AphrontView {

  private $items = array();
  private $authorityPackages;

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

  public function render() {
    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-table-of-contents-css');
    $this->requireResource('phui-text-css');

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

    $reveal_link = javelin_tag(
      'a',
      array(
        'sigil' => 'differential-reveal-all',
        'mustcapture' => true,
        'class' => 'button differential-toc-reveal-all',
      ),
      pht('Show All Context'));

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'differential-toc-buttons grouped',
      ),
      $reveal_link);

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

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Table of Contents'))
      ->setTable($table)
      ->appendChild($anchor)
      ->appendChild($buttons);
  }

}
