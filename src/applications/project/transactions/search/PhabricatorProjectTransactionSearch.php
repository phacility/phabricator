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

class PhabricatorProjectTransactionSearch {
  private $projectPhids;
  private $documents;
  private $status;

  public function __construct($project_phids) {
    if (is_array($project_phids)) {
      $this->projectPhids = $project_phids;
    } else {
      $this->projectPhids = array($project_phids);
    }
    return $this;
  }

  // search all open documents by default
  public function setSearchOptions($documents = '', $status = true) {
    $this->documents = $documents;
    $this->status = $status;
    return $this;
  }

  public function executeSearch() {
    $projects = $this->projectPhids;
    $on_documents = $this->documents;
    $with_status = $this->status;

    $query = new PhabricatorSearchQuery();
    $query->setQuery('');
    $query->setParameter('project', $projects);
    $query->setParameter('type', $on_documents);
    $query->setParameter('open', $with_status);

    $executor = new PhabricatorSearchMySQLExecutor();
    $results = $executor->executeSearch($query);
    return $results;
  }
}
