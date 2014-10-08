<?php

interface PhabricatorDestructibleInterface {

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine);

}


// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */
/*

  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    <<<$this->nuke();>>>

  }

*/
