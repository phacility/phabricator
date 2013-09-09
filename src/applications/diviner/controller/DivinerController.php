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

    $list = id(new PHUIObjectItemListView())
      ->setUser($user);

    foreach ($symbols as $symbol) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($symbol->getTitle())
        ->setHref($symbol->getURI())
        ->addIcon('none',
          DivinerAtom::getAtomTypeNameString(
            $symbol->getType()));

      $item->addAttribute($symbol->getSummary());

      $list->addItem($item);
    }

    return $list;
  }

}
