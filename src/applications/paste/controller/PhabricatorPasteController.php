<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildApplicationMenu() {
    return $this->newApplicationMenu()
      ->setSearchEngine(new PhabricatorPasteSearchEngine());
  }

  public function buildSourceCodeView(
    PhabricatorPaste $paste,
    $highlights = array()) {

    $lines = phutil_split_lines($paste->getContent());

    return id(new PhabricatorSourceCodeView())
      ->setLines($lines)
      ->setHighlights($highlights)
      ->setURI(new PhutilURI($paste->getURI()));
  }

}
