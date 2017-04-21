<?php

interface PhabricatorFulltextInterface
  extends PhabricatorIndexableInterface {

  public function newFulltextEngine();

}
