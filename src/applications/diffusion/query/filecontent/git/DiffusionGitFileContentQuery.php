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

    if ($this->getNeedsBlame()) {
      list($corpus) = $repository->execxLocalCommand(
        '--no-pager blame -c -l --date=short %s -- %s',
        $commit,
        $path);
    } else {
      list($corpus) = $repository->execxLocalCommand(
        'cat-file blob %s:%s',
        $commit,
        $path);
    }

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }


  protected function tokenizeLine($line) {
    $m = array();
    // sample lines:
    //
    // d1b4fcdd2a7c8c0f8cbdd01ca839d992135424dc
    //                       (     hzhao   2009-05-01  202)function print();
    //
    // 8220d5d54f6d5d5552a636576cbe9c35f15b65b2
    //                       (Andrew Gallagher       2010-12-03      324)
    //                             // Add the lines for trailing context
    preg_match('/^\s*?(\S+?)\s*\(\s*([^)]*)\s+\d{4}-\d{2}-\d{2}\s+\d+\)(.*)?$/',
               $line, $m);
    $rev_id = $m[1];
    $author = $m[2];
    $text = idx($m, 3);
    return array($rev_id, $author, $text);
  }

}
