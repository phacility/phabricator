<?php

final class DiffusionDiffQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  private $effectiveCommit;

  public function getAPIMethodName() {
    return 'diffusion.diffquery';
  }

  public function getMethodDescription() {
    return pht(
      'Get diff information from a repository for a specific path at an '.
      '(optional) commit.');
  }

  protected function defineReturnType() {
    return 'array';
  }

  protected function defineCustomParamTypes() {
    return array(
      'path' => 'required string',
      'commit' => 'optional string',
    );
  }

  protected function getResult(ConduitAPIRequest $request) {
    $result = parent::getResult($request);

    return array(
      'changes' => mpull($result, 'toDictionary'),
      'effectiveCommit' => $this->getEffectiveCommit($request),
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    return $this->getGitOrMercurialResult($request);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    return $this->getGitOrMercurialResult($request);
  }

  /**
   * NOTE: We have to work particularly hard for SVN as compared to other VCS.
   * That's okay but means this shares little code with the other VCS.
   */
  protected function getSVNResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $effective_commit = $this->getEffectiveCommit($request);
    if (!$effective_commit) {
      return $this->getEmptyResult();
    }

    $drequest = clone $drequest;
    $drequest->updateSymbolicCommit($effective_commit);

    $path_change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
      $drequest);
    $path_changes = $path_change_query->loadChanges();

    $path = null;
    foreach ($path_changes as $change) {
      if ($change->getPath() == $drequest->getPath()) {
        $path = $change;
      }
    }

    if (!$path) {
      return $this->getEmptyResult();
    }

    $change_type = $path->getChangeType();
    switch ($change_type) {
      case DifferentialChangeType::TYPE_MULTICOPY:
      case DifferentialChangeType::TYPE_DELETE:
        if ($path->getTargetPath()) {
          $old = array(
            $path->getTargetPath(),
            $path->getTargetCommitIdentifier(),
          );
        } else {
          $old = array($path->getPath(), $path->getCommitIdentifier() - 1);
        }
        $old_name = $path->getPath();
        $new_name = '';
        $new = null;
        break;
      case DifferentialChangeType::TYPE_ADD:
        $old = null;
        $new = array($path->getPath(), $path->getCommitIdentifier());
        $old_name = '';
        $new_name = $path->getPath();
        break;
      case DifferentialChangeType::TYPE_MOVE_HERE:
      case DifferentialChangeType::TYPE_COPY_HERE:
        $old = array(
          $path->getTargetPath(),
          $path->getTargetCommitIdentifier(),
        );
        $new = array($path->getPath(), $path->getCommitIdentifier());
        $old_name = $path->getTargetPath();
        $new_name = $path->getPath();
        break;
      case DifferentialChangeType::TYPE_MOVE_AWAY:
        $old = array(
          $path->getPath(),
          $path->getCommitIdentifier() - 1,
        );
        $old_name = $path->getPath();
        $new_name = null;
        $new = null;
        break;
      default:
        $old = array($path->getPath(), $path->getCommitIdentifier() - 1);
        $new = array($path->getPath(), $path->getCommitIdentifier());
        $old_name = $path->getPath();
        $new_name = $path->getPath();
        break;
    }

    $futures = array(
      'old' => $this->buildSVNContentFuture($old),
      'new' => $this->buildSVNContentFuture($new),
    );
    $futures = array_filter($futures);

    foreach (new FutureIterator($futures) as $key => $future) {
      $stdout = '';
      try {
        list($stdout) = $future->resolvex();
      } catch (CommandException $e) {
        if ($path->getFileType() != DifferentialChangeType::FILE_DIRECTORY) {
          throw $e;
        }
      }
      $futures[$key] = $stdout;
    }

    $old_data = idx($futures, 'old', '');
    $new_data = idx($futures, 'new', '');

    $engine = new PhabricatorDifferenceEngine();
    $engine->setOldName($old_name);
    $engine->setNewName($new_name);
    $raw_diff = $engine->generateRawDiffFromFileContent($old_data, $new_data);

    $arcanist_changes = DiffusionPathChange::convertToArcanistChanges(
      $path_changes);

    $parser = $this->getDefaultParser();
    $parser->setChanges($arcanist_changes);
    $parser->forcePath($path->getPath());
    $changes = $parser->parseDiff($raw_diff);

    $change = $changes[$path->getPath()];

    return array($change);
  }

  private function getEffectiveCommit(ConduitAPIRequest $request) {
    if ($this->effectiveCommit === null) {
      $drequest = $this->getDiffusionRequest();

      $path = $drequest->getPath();
      $result = DiffusionQuery::callConduitWithDiffusionRequest(
        $request->getUser(),
        $drequest,
        'diffusion.lastmodifiedquery',
        array(
          'paths' => array($path => $drequest->getStableCommit()),
        ));

      $this->effectiveCommit = idx($result, $path);
    }

    return $this->effectiveCommit;
  }

  private function buildSVNContentFuture($spec) {
    if (!$spec) {
      return null;
    }

    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    list($ref, $rev) = $spec;
    return $repository->getRemoteCommandFuture(
      'cat %s',
      $repository->getSubversionPathURI($ref, $rev));
  }

  private function getGitOrMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $effective_commit = $this->getEffectiveCommit($request);
    if (!$effective_commit) {
      return $this->getEmptyResult();
    }

    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest)
      ->setAnchorCommit($effective_commit);

    $raw_diff = $raw_query->executeInline();
    if (!$raw_diff) {
      return $this->getEmptyResult();
    }

    $parser = $this->getDefaultParser();
    $changes = $parser->parseDiff($raw_diff);

    return $changes;
  }

  private function getDefaultParser() {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $parser = new ArcanistDiffParser();
    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
      $parser->setTryEncoding($try_encoding);
    }
    $parser->setDetectBinaryFiles(true);

    return $parser;
  }

  private function getEmptyResult() {
    return array();
  }

}
