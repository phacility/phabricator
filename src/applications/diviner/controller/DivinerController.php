<?php

abstract class DivinerController extends PhabricatorController {

  protected function buildSideNavView() {
    $menu = $this->buildApplicationMenu();
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  public function buildApplicationMenu() {
    $menu = new PHUIListView();

    id(new DivinerAtomSearchEngine())
      ->setViewer($this->getRequest()->getViewer())
      ->addNavigationItems($menu);

    return $menu;
  }

  protected function renderAtomList(array $symbols) {
    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $list = array();
    foreach ($symbols as $symbol) {
      switch ($symbol->getType()) {
        case DivinerAtom::TYPE_FUNCTION:
          $title = $symbol->getTitle().'()';
          break;
        default:
          $title = $symbol->getTitle();
          break;
      }

      $item = id(new DivinerBookItemView())
        ->setTitle($title)
        ->setHref($symbol->getURI())
        ->setSubtitle($symbol->getSummary())
        ->setType(DivinerAtom::getAtomTypeNameString($symbol->getType()));

      $list[] = $item;
    }

    return $list;
  }

}
