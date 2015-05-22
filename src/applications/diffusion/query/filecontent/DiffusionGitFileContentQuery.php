<?php

final class DiffusionGitFileContentQuery extends DiffusionFileContentQuery {

  public function getFileContentFuture() {
    $drequest = $this->getRequest();

    $repository = $drequest->getRepository();
    $path = $drequest->getPath();
    $commit = $drequest->getCommit();

    if ($this->getNeedsBlame()) {
      return $repository->getLocalCommandFuture(
        '--no-pager blame -c -l --date=short %s -- %s',
        $commit,
        $path);
    } else {
      return $repository->getLocalCommandFuture(
        'cat-file blob %s:%s',
        $commit,
        $path);
    }
  }

  protected function executeQueryFromFuture(Future $future) {
    list($corpus) = $future->resolvex();

    $file_content = new DiffusionFileContent();
    $file_content->setCorpus($corpus);

    return $file_content;
  }

  protected function tokenizeLine($line) {
    return self::match($line);
  }

  public static function match($line) {
    $m = array();
    // sample lines:
    //
    // d1b4fcdd2a7c8c0f8cbdd01ca839d992135424dc
    //                       (     hzhao   2009-05-01  202)function print();
    //
    // 8220d5d54f6d5d5552a636576cbe9c35f15b65b2
    //                       (Andrew Gallagher       2010-12-03      324)
    //                             // Add the lines for trailing context
    preg_match(
      '/^\s*?(\S+?)\s*\(\s*(.*?)\s+\d{4}-\d{2}-\d{2}\s+\d+\)(.*)?$/',
      $line,
      $m);
    $rev_id = $m[1];
    $author = $m[2];
    $text = idx($m, 3);
    return array($rev_id, $author, $text);
  }

}
