<?php

abstract class PhabricatorFilesBuiltinFile extends Phobject {

  abstract public function getBuiltinFileKey();
  abstract public function getBuiltinDisplayName();
  abstract public function loadBuiltinFileData();

}
