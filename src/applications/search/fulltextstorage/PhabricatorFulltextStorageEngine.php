<?php

/**
 * Base class for Phabricator search engine providers. Each engine must offer
 * three capabilities: indexing, searching, and reconstruction (this can be
 * stubbed out if an engine can't reasonably do it, it is used for debugging).
 */
abstract class PhabricatorFulltextStorageEngine extends Phobject {

/* -(  Engine Metadata  )---------------------------------------------------- */

  /**
   * Return a unique, nonempty string which identifies this storage engine.
   *
   * @return string Unique string for this engine, max length 32.
   * @task meta
   */
  abstract public function getEngineIdentifier();

  /**
   * Prioritize this engine relative to other engines.
   *
   * Engines with a smaller priority number get an opportunity to write files
   * first. Generally, lower-latency filestores should have lower priority
   * numbers, and higher-latency filestores should have higher priority
   * numbers. Setting priority to approximately the number of milliseconds of
   * read latency will generally produce reasonable results.
   *
   * In conjunction with filesize limits, the goal is to store small files like
   * profile images, thumbnails, and text snippets in lower-latency engines,
   * and store large files in higher-capacity engines.
   *
   * @return float Engine priority.
   * @task meta
   */
  abstract public function getEnginePriority();

  /**
   * Return `true` if the engine is currently writable.
   *
   * Engines that are disabled or missing configuration should return `false`
   * to prevent new writes. If writes were made with this engine in the past,
   * the application may still try to perform reads.
   *
   * @return bool True if this engine can support new writes.
   * @task meta
   */
  abstract public function isEnabled();


/* -(  Managing Documents  )------------------------------------------------- */

  /**
   * Update the index for an abstract document.
   *
   * @param PhabricatorSearchAbstractDocument Document to update.
   * @return void
   */
  abstract public function reindexAbstractDocument(
    PhabricatorSearchAbstractDocument $document);

  /**
   * Reconstruct the document for a given PHID. This is used for debugging
   * and does not need to be perfect if it is unreasonable to implement it.
   *
   * @param  phid Document PHID to reconstruct.
   * @return PhabricatorSearchAbstractDocument Abstract document.
   */
  abstract public function reconstructDocument($phid);

  /**
   * Execute a search query.
   *
   * @param PhabricatorSavedQuery A query to execute.
   * @return list A list of matching PHIDs.
   */
  abstract public function executeSearch(PhabricatorSavedQuery $query);

  /**
   * Does the search index exist?
   *
   * @return bool
   */
  abstract public function indexExists();

  /**
   * Is the index in a usable state?
   *
   * @return bool
   */
  public function indexIsSane() {
    return $this->indexExists();
  }

  /**
   * Do any sort of setup for the search index.
   *
   * @return void
   */
  public function initIndex() {}


/* -(  Loading Storage Engines  )-------------------------------------------- */

  /**
   * @task load
   */
  public static function loadAllEngines() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getEngineIdentifier')
      ->setSortMethod('getEnginePriority')
      ->execute();
  }

  /**
   * @task load
   */
  public static function loadActiveEngines() {
    $engines = self::loadAllEngines();

    $active = array();
    foreach ($engines as $key => $engine) {
      if (!$engine->isEnabled()) {
        continue;
      }

      $active[$key] = $engine;
    }

    return $active;
  }

  public static function loadEngine() {
    return head(self::loadActiveEngines());
  }

}
