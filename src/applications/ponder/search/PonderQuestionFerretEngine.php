<?php

final class PonderQuestionFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'ponder';
  }

  public function getScopeName() {
    return 'question';
  }

  public function newSearchEngine() {
    return new PonderQuestionSearchEngine();
  }

}
