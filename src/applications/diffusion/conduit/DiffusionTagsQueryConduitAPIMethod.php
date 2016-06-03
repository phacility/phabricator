<?php

final class DiffusionTagsQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.tagsquery';
  }

  public function getMethodDescription() {
    return pht('Retrieve information about tags in a repository.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'names' => 'optional list<string>',
      'commit' => 'optional string',
      'needMessages' => 'optional bool',
      'offset' => 'optional int',
      'limit' => 'optional int',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $commit = $drequest->getSymbolicCommit();

    $commit_filter = null;
    if ($commit) {
      $commit_filter = $this->loadTagNamesForCommit($commit);
    }

    $name_filter = $request->getValue('names', null);

    $all_tags = $this->loadGitTagList();
    $all_tags = mpull($all_tags, null, 'getName');

    if ($name_filter !== null) {
      $all_tags = array_intersect_key($all_tags, array_fuse($name_filter));
    }
    if ($commit_filter !== null) {
      $all_tags = array_intersect_key($all_tags, $commit_filter);
    }

    $tags = array_values($all_tags);

    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');
    if ($offset) {
      $tags = array_slice($tags, $offset);
    }

    if ($limit) {
      $tags = array_slice($tags, 0, $limit);
    }

    if ($request->getValue('needMessages')) {
      $this->loadMessagesForTags($all_tags);
    }

    return mpull($tags, 'toDictionary');
  }

  private function loadGitTagList() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withRefTypes(
        array(
          PhabricatorRepositoryRefCursor::TYPE_TAG,
        ))
      ->execute();

    $tags = array();
    foreach ($refs as $ref) {
      $fields = $ref->getRawFields();
      $tag = id(new DiffusionRepositoryTag())
        ->setAuthor($fields['author'])
        ->setEpoch($fields['epoch'])
        ->setCommitIdentifier($ref->getCommitIdentifier())
        ->setName($ref->getShortName())
        ->setDescription($fields['subject'])
        ->setType('git/'.$fields['objecttype']);

      $tags[] = $tag;
    }

    return $tags;
  }

  private function loadTagNamesForCommit($commit) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    list($err, $stdout) = $repository->execLocalCommand(
      'tag -l --contains %s',
      $commit);

    if ($err) {
      // Git exits with an error code if the commit is bogus.
      return array();
    }

    $stdout = rtrim($stdout, "\n");
    if (!strlen($stdout)) {
      return array();
    }

    $tag_names = explode("\n", $stdout);
    $tag_names = array_fill_keys($tag_names, true);

    return $tag_names;
  }

  private function loadMessagesForTags(array $tags) {
    assert_instances_of($tags, 'DiffusionRepositoryTag');

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $futures = array();
    foreach ($tags as $key => $tag) {
      $futures[$key] = $repository->getLocalCommandFuture(
        'cat-file tag %s',
        $tag->getName());
    }

    id(new FutureIterator($futures))
      ->resolveAll();

    foreach ($tags as $key => $tag) {
      $future = $futures[$key];
      list($err, $stdout) = $future->resolve();

      $message = null;
      if ($err) {
        // Not all tags are actually "tag" objects: a "tag" object is only
        // created if you provide a message or sign the tag. Tags created with
        // `git tag x [commit]` are "lightweight tags" and `git cat-file tag`
        // will fail on them. This is fine: they don't have messages.
      } else {
        $parts = explode("\n\n", $stdout, 2);
        if (count($parts) == 2) {
          $message = last($parts);
        }
      }

      $tag->attachMessage($message);
    }

    return $tags;
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    // For now, we don't support Mercurial tags via API.
    return array();
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    // Subversion has no meaningful concept of tags.
    return array();
  }

}
