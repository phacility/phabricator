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

final class DiffusionGitCommitTagsQuery
  extends DiffusionCommitTagsQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($err, $stdout) = $repository->execLocalCommand(
      'tag -l --contains %s',
      $drequest->getCommit());

    if ($err) {
      // Git exits with an error code if the commit is bogus.
      return array();
    }

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      return array();
    }

    $tag_names = explode("\n", $stdout);
    $tag_names = array_fill_keys($tag_names, true);

    $tag_query = DiffusionTagListQuery::newFromDiffusionRequest($drequest);
    $tags = $tag_query->loadTags();

    $result = array();
    foreach ($tags as $tag) {
      if (isset($tag_names[$tag->getName()])) {
        $result[] = $tag;
      }
    }

    if ($this->getOffset()) {
      $result = array_slice($result, $this->getOffset());
    }

    if ($this->getLimit()) {
      $result = array_slice($result, 0, $this->getLimit());
    }

    return $result;
  }

}
