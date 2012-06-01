<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


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
