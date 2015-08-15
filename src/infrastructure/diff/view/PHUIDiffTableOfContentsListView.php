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

    $items = $this->items;

    $rows = array();
    foreach ($items as $item) {
      $rows[] = $item->render();
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
          '',
          '',
          '',
          pht('Path'),
          pht('Coverage (All)'),
          pht('Coverage (Touched)'),
        ))
      ->setColumnClasses(
        array(
          'differential-toc-char center',
          'differential-toc-prop center',
          'differential-toc-ftype center',
          'differential-toc-file wide',
          'differential-toc-cov',
          'differential-toc-cov',
        ))
      ->setDeviceVisibility(
        array(
          true,
          true,
          true,
          true,
          false,
          false,
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
