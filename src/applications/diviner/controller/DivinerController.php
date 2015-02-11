<?php

abstract class DivinerController extends PhabricatorController {

  protected function buildSideNavView() {
    $menu = $this->buildMenu();
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  public function buildApplicationMenu() {
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
        ->setType(DivinerAtom::getAtomTypeNameString(
            $symbol->getType()));

      $list[] = $item;
    }

    return $list;
  }

}
