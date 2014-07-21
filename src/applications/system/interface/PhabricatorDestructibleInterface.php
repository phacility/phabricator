<?php

interface PhabricatorDestructibleInterface {

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine);

}


// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorDestructableInterface  )----------------------------------- */
/*

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    <<<$this->nuke();>>>

  }

*/
