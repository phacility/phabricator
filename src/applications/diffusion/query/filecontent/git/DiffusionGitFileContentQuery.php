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

final class DiffusionGitFileContentQuery extends DiffusionFileContentQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    $local_path = $repository->getDetail('local-path');
    if ($this->getNeedsBlame()) {
      list($corpus) = execx(
        '(cd %s && git --no-pager blame -c -l --date short %s -- %s)',
        $local_path,
        $commit,
        $path);
    } else {
      list($corpus) = execx(
        '(cd %s && git cat-file blob %s:%s)',
        $local_path,
        $commit,
        $path);
    }

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }


  protected function tokenizeLine($line) {
    $m = array();
    // sample line:
    // d1b4fcdd2a7c8c0f8cbdd01ca839d992135424dc
    //                       (     hzhao   2009-05-01  202)function print();
    preg_match('/^\s*?(\S+?)\s*\(\s*(\S+)\s+\S+\s+\d+\)(.*)?$/', $line, $m);
    $rev_id = $m[1];
    $author = $m[2];
    $text = idx($m, 3);
    return array($rev_id, $author, $text);
  }

}
