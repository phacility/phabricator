<?php

abstract class DrydockFilesystemInterface extends DrydockInterface {

  final public function getInterfaceType() {
    return 'filesystem';
  }

  /**
   * Reads a file on the Drydock resource and returns the contents of the file.
   */
  abstract public function readFile($path);

  /**
   * Reads a file on the Drydock resource and saves it as a PhabricatorFile.
   */
  abstract public function saveFile($path, $name);

  /**
   * Writes a file to the Drydock resource.
   */
  abstract public function writeFile($path, $data);

}
