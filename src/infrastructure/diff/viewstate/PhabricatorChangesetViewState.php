<?php

final class PhabricatorChangesetViewState
  extends Phobject {

  private $highlightLanguage;

  public function setHighlightLanguage($highlight_language) {
    $this->highlightLanguage = $highlight_language;
    return $this;
  }

  public function getHighlightLanguage() {
    return $this->highlightLanguage;
  }

}
