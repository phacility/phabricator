<?php

/**
 * @group conduit
 */
final class ConduitAPI_diffusion_tagsquery_Method
  extends ConduitAPI_diffusion_abstractquery_Method {

  public function getMethodDescription() {
    return
      'Find tags for a given commit or list tags in the repository.';
  }

  public function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'commit' => 'optional string',
      'offset' => 'optional int',
      'limit' => 'optional int',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $drequest->getRawCommit();

    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    if (!$commit) {
      return $this->loadGitTagList($offset, $limit);
    }

    list($err, $stdout) = $repository->execLocalCommand(
      'tag -l --contains %s',
      $commit);

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

    $tags = $this->loadGitTagList($offset = 0, $limit = 0, $serialize = false);

    $result = array();
    foreach ($tags as $tag) {
      if (isset($tag_names[$tag->getName()])) {
        $result[] = $tag->toDictionary();
      }
    }

    if ($offset) {
      $result = array_slice($result, $offset);
    }
    if ($limit) {
      $result = array_slice($result, 0, $limit);
    }

    return $result;
  }

  private function loadGitTagList($offset, $limit, $serialize=true) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $count = $offset + $limit;

    list($stdout) = $repository->execxLocalCommand(
      'for-each-ref %C --sort=-creatordate --format=%s refs/tags',
      $count ? '--count='.(int)$count : null,
      '%(objectname) %(objecttype) %(refname) %(*objectname) %(*objecttype) '.
        '%(subject)%01%(creator)');

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

    if ($offset) {
      $tags = array_slice($tags, $offset);
    }

    if ($serialize) {
      $tags = mpull($tags, 'toDictionary');
    }
    return $tags;
  }

}
