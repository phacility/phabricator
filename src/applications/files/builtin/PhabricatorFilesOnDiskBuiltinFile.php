<?php

final class PhabricatorFilesOnDiskBuiltinFile
  extends PhabricatorFilesBuiltinFile {

  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    if ($this->name === null) {
      throw new PhutilInvalidStateException('setName');
    }

    return $this->name;
  }

  public function getBuiltinDisplayName() {
    return $this->getName();
  }

  public function getBuiltinFileKey() {
    $name = $this->getName();
    $desc = "disk(name={$name})";
    $hash = PhabricatorHash::digestToLength($desc, 40);
    return "builtin:{$hash}";
  }

  public function loadBuiltinFileData() {
    $name = $this->getName();

    $available = $this->getAllBuiltinFiles();
    if (empty($available[$name])) {
      throw new Exception(pht('Builtin "%s" does not exist!', $name));
    }

    return Filesystem::readFile($available[$name]);
  }

  private function getAllBuiltinFiles() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/';

    $map = array();
    $list = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->find();

    foreach ($list as $file) {
      $map[$file] = $root.$file;
    }
    return $map;
  }

  public function getProjectBuiltinFiles() {
    $root = dirname(phutil_get_library_root('phabricator'));
    $root = $root.'/resources/builtin/projects/';

    $map = array();
    $list = id(new FileFinder($root))
      ->withType('f')
      ->withFollowSymlinks(true)
      ->find();

    foreach ($list as $file) {
      $map[$file] = $root.$file;
    }
    return $map;
  }

}
