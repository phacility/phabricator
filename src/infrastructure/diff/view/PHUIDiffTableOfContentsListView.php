<?php

final class PHUIDiffTableOfContentsListView extends AphrontView {

  private $items = array();

  public function addItem(PHUIDiffTableOfContentsItemView $item) {
    $this->items[] = $item;
    return $this;
  }

  public function render() {
    $this->requireResource('differential-core-view-css');
    $this->requireResource('differential-table-of-contents-css');
    $this->requireResource('phui-text-css');

    Javelin::initBehavior('phabricator-tooltips');

    $items = $this->items;

    $rows = array();
    foreach ($items as $item) {
      $item->setUser($this->getUser());
      $rows[] = $item->render();
    }

    // Check if any item has content in these columns. If no item does, we'll
    // just hide them.
    $any_coverage = false;
    $any_context = false;
    $any_package = false;
    foreach ($items as $item) {
      if ($item->getContext() !== null) {
        $any_context = true;
      }

      if (strlen($item->getCoverage())) {
        $any_coverage = true;
      }

      if ($item->getPackage() !== null) {
        $any_package = true;
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
      ->setHeaders(
        array(
          null,
          null,
          null,
          null,
          pht('Path'),
          pht('Coverage (All)'),
          pht('Coverage (Touched)'),
          null,
        ))
      ->setColumnClasses(
        array(
          'center',
          'differential-toc-char center',
          'differential-toc-prop center',
          'differential-toc-ftype center',
          'differential-toc-file wide',
          'differential-toc-cov',
          'differential-toc-cov',
          'center',
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
          $any_package,
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
