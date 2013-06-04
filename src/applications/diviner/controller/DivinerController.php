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
    $menu = new PhabricatorMenuView();

    id(new DivinerAtomSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($menu);

    return $menu;
  }

  protected function renderAtomList(array $symbols) {
    assert_instances_of($symbols, 'DivinerLiveSymbol');

    $request = $this->getRequest();
    $user = $request->getUser();

    $list = id(new PhabricatorObjectItemListView())
      ->setUser($user);

    foreach ($symbols as $symbol) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($symbol->getTitle())
        ->setHref($symbol->getURI())
        ->addIcon('none', $symbol->getType());

      $item->addAttribute(phutil_safe_html($symbol->getSummary()));

      $list->addItem($item);
    }

    return $list;
  }

}
