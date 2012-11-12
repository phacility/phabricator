<?php

/**
 * Chooses appropriate storage engine(s) for files. When Phabricator needs
 * to write a blob of file data, it uses the configured selector to get a list
 * of suitable @{class:PhabricatorFileStorageEngine}s. For more information,
 * see @{article:File Storage Technical Documentation}.
 *
 * @group filestorage
 * @task  select  Selecting Storage Engines
 */
abstract class PhabricatorFileStorageEngineSelector {

  final public function __construct() {
    // <empty>
  }


/* -(  Selecting Storage Engines  )------------------------------------------ */


  /**
   * Select valid storage engines for a file. This method will be called by
   * Phabricator when it needs to store a file permanently. It must return a
   * list of valid @{class:PhabricatorFileStorageEngine}s.
   *
   * If you are are extending this class to provide a custom selector, you
   * probably just want it to look like this:
   *
   *   return array(new MyCustomFileStorageEngine());
   *
   * ...that is, store every file in whatever storage engine you're using.
   * However, you can also provide multiple storage engines, or store some files
   * in one engine and some files in a different engine by implementing a more
   * complex selector.
   *
   * @param string  File data.
   * @param dict    Dictionary of optional file metadata. This may be empty, or
   *                have some additional keys like 'file' and 'author' which
   *                provide metadata.
   * @return list   List of @{class:PhabricatorFileStorageEngine}s, ordered by
   *                preference.
   * @task select
   */
  abstract public function selectStorageEngines($data, array $params);

}
