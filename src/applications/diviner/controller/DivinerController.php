<?php

abstract class DivinerController extends PhabricatorController {

  protected function buildSideNavView() {
    $menu = $this->buildMenu();
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  protected function buildApplicationMenu() {
    return $this->buildMenu();
  }

  private function buildMenu() {
    $menu = new PHUIListView();

    id(new DivinerAtomSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($menu);

    return $menu;
  }

  protected function renderAtomList(array $symbols) {
    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $request = $this->getRequest();
    $user = $request->getUser();

    $list = array();
    foreach ($symbols as $symbol) {
      $item = id(new DivinerBookItemView())
        ->setTitle($symbol->getTitle())
        ->setHref($symbol->getURI())
        ->setSubtitle($symbol->getSummary())
        ->setType(DivinerAtom::getAtomTypeNameString(
            $symbol->getType()));

      $list[] = $item;
    }

    return $list;
  }

}
