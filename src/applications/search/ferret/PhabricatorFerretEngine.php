<?php

abstract class PhabricatorFerretEngine extends Phobject {

  abstract public function newNgramsObject();
  abstract public function newDocumentObject();
  abstract public function newFieldObject();

}
