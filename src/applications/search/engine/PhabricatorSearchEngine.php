<?php


/**
 * Base class for Phabricator search engine providers. Each engine must offer
 * three capabilities: indexing, searching, and reconstruction (this can be
 * stubbed out if an engine can't reasonably do it, it is used for debugging).
 *
 * @group search
 */
abstract class PhabricatorSearchEngine {

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
   * @param PhabricatorSearchQuery A query to execute.
   * @return list A list of matching PHIDs.
   */
  abstract public function executeSearch(PhabricatorSearchQuery $query);

}
