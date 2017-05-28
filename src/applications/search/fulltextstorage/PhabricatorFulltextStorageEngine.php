<?php

/**
 * Base class for Phabricator search engine providers. Each engine must offer
 * three capabilities: indexing, searching, and reconstruction (this can be
 * stubbed out if an engine can't reasonably do it, it is used for debugging).
 */
abstract class PhabricatorFulltextStorageEngine extends Phobject {

  protected $service;

  public function getHosts() {
    return $this->service->getHosts();
  }

  public function setService(PhabricatorSearchService $service) {
    $this->service = $service;
    return $this;
  }

  /**
   * @return PhabricatorSearchService
   */
  public function getService() {
    return $this->service;
  }

  /**
   * Implementations must return a prototype host instance which is cloned
   * by the PhabricatorSearchService infrastructure to configure each engine.
   * @return PhabricatorSearchHost
   */
  abstract public function getHostType();

/* -(  Engine Metadata  )---------------------------------------------------- */

  /**
   * Return a unique, nonempty string which identifies this storage engine.
   *
   * @return string Unique string for this engine, max length 32.
   * @task meta
   */
  abstract public function getEngineIdentifier();

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
    * Implementations should override this method to return a dictionary of
    * stats which are suitable for display in the admin UI.
    */
  abstract public function getIndexStats();


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


  public function getFulltextTokens() {
    return array();
  }

}
