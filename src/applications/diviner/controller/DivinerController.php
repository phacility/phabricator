<?php

abstract class DivinerController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new DivinerAtomSearchEngine());
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
