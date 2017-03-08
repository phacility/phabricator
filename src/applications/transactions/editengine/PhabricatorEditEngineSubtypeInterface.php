<?php

interface PhabricatorEditEngineSubtypeInterface {

  public function getEditEngineSubtype();
  public function setEditEngineSubtype($subtype);
  public function newEditEngineSubtypeMap();

}
