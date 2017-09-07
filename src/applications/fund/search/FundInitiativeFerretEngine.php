<?php

final class FundInitiativeFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new FundInitiativeFerretNgrams();
  }

  public function newDocumentObject() {
    return new FundInitiativeFerretDocument();
  }

  public function newFieldObject() {
    return new FundInitiativeFerretField();
  }

  public function newSearchEngine() {
    return new FundInitiativeSearchEngine();
  }

}
