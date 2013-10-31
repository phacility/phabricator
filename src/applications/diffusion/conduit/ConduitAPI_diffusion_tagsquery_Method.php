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

    $refs = id(new DiffusionLowLevelGitRefQuery())
      ->setRepository($repository)
      ->withIsTag(true)
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

    if ($offset) {
      $tags = array_slice($tags, $offset);
    }

    if ($limit) {
      $tags = array_slice($tags, 0, $limit);
    }

    if ($serialize) {
      $tags = mpull($tags, 'toDictionary');
    }
    return $tags;
  }

}
