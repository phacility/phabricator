<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class DiffusionSvnLastModifiedQuery extends DiffusionLastModifiedQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $path = $drequest->getPath();

    $history_query = DiffusionHistoryQuery::newFromDiffusionRequest(
      $drequest);
    $history_query->setLimit(1);
    $history_query->needChildChanges(true);
    $history_query->needDirectChanges(true);
    $history_array = $history_query->loadHistory();

    if (!$history_array) {
      return array(null, null);
    }

    $history = reset($history_array);

    return array($history->getCommit(), $history->getCommitData());
  }

}
