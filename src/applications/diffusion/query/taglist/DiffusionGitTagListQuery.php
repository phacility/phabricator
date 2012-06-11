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

final class DiffusionGitTagListQuery extends DiffusionTagListQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $count = $this->getOffset() + $this->getLimit();

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref %C --sort=-creatordate --format=%s refs/tags',
      $count ? '--count='.(int)$count : null,
      '%(objectname) %(objecttype) %(refname) %(*objectname) %(*objecttype) '.
        '%(subject)%01%(creator)'
    );

    $stdout = trim($stdout);
    if (!strlen($stdout)) {
      return array();
    }

    $tags = array();
    foreach (explode("\n", $stdout) as $line) {
      list($info, $creator) = explode("\1", $line);
      list(
        $objectname,
        $objecttype,
        $refname,
        $refobjectname,
        $refobjecttype,
        $description) = explode(' ', $info, 6);

      $matches = null;
      if (!preg_match('/^(.*) ([0-9]+) ([0-9+-]+)$/', $creator, $matches)) {
        // It's possible a tag doesn't have a creator (tagger)
        $author = null;
        $epoch = null;
      } else {
        $author = $matches[1];
        $epoch  = $matches[2];
      }

      $tag = new DiffusionRepositoryTag();
      $tag->setAuthor($author);
      $tag->setEpoch($epoch);
      $tag->setCommitIdentifier(nonempty($refobjectname, $objectname));
      $tag->setName(preg_replace('@^refs/tags/@', '', $refname));
      $tag->setDescription($description);
      $tag->setType('git/'.$objecttype);

      $tags[] = $tag;
    }

    $offset = $this->getOffset();
    if ($offset) {
      $tags = array_slice($tags, $offset);
    }

    return $tags;
  }

}
