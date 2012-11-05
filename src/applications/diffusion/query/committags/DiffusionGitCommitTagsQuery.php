<?php

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
