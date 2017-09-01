<?php

final class ManiphestTaskFerretEngine
  extends PhabricatorFerretEngine {

  public function newNgramsObject() {
    return new ManiphestTaskFerretNgrams();
  }

  public function newDocumentObject() {
    return new ManiphestTaskFerretDocument();
  }

  public function newFieldObject() {
    return new ManiphestTaskFerretField();
  }

}
