<?php

abstract class PhabricatorRepositoryGraphStream extends Phobject {

  abstract public function getParents($commit);
  abstract public function getCommitDate($commit);

}
