<?php

abstract class PhabricatorFilesManagementWorkflow
  extends PhutilArgumentWorkflow {

  public function isExecutable() {
    return true;
  }

  protected function buildIterator(PhutilArgumentParser $args) {
    if ($args->getArg('all')) {
      if ($args->getArg('names')) {
        throw new PhutilArgumentUsageException(
          "Specify either a list of files or `--all`, but not both.");
      }
      return new LiskMigrationIterator(new PhabricatorFile());
    }

    if ($args->getArg('names')) {
      $iterator = array();

      foreach ($args->getArg('names') as $name) {
        $name = trim($name);

        $id = preg_replace('/^F/i', '', $name);
        if (ctype_digit($id)) {
          $file = id(new PhabricatorFile())->loadOneWhere(
            'id = %d',
            $id);
          if (!$file) {
            throw new PhutilArgumentUsageException(
              "No file exists with ID '{$name}'.");
          }
        } else {
          $file = id(new PhabricatorFile())->loadOneWhere(
            'phid = %s',
            $name);
          if (!$file) {
            throw new PhutilArgumentUsageException(
              "No file exists with PHID '{$name}'.");
          }
        }
        $iterator[] = $file;
      }

      return $iterator;
    }

    return null;
  }


}
