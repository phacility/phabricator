<?php

final class DiffusionSvnDiffQuery extends DiffusionDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $effective_commit = $this->getEffectiveCommit();
    if (!$effective_commit) {
      return null;
    }

    $drequest = clone $drequest;
    $drequest->setCommit($effective_commit);

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
      return null;
    }

    $change_type = $path->getChangeType();
    switch ($change_type) {
      case DifferentialChangeType::TYPE_MULTICOPY:
      case DifferentialChangeType::TYPE_DELETE:
        if ($path->getTargetPath()) {
          $old = array(
            $path->getTargetPath(),
            $path->getTargetCommitIdentifier());
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
          $path->getTargetCommitIdentifier());
        $new = array($path->getPath(), $path->getCommitIdentifier());
        $old_name = $path->getTargetPath();
        $new_name = $path->getPath();
        break;
      case DifferentialChangeType::TYPE_MOVE_AWAY:
        $old = array(
          $path->getPath(),
          $path->getCommitIdentifier() - 1);
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
      'old' => $this->buildContentFuture($old),
      'new' => $this->buildContentFuture($new),
    );
    $futures = array_filter($futures);

    foreach (Futures($futures) as $key => $future) {
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

    $parser = new ArcanistDiffParser();

    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
      $parser->setTryEncoding($try_encoding);
    }

    $parser->setDetectBinaryFiles(true);

    $arcanist_changes = DiffusionPathChange::convertToArcanistChanges(
      $path_changes);

    $parser->setChanges($arcanist_changes);
    $parser->forcePath($path->getPath());
    $changes = $parser->parseDiff($raw_diff);

    $change = $changes[$path->getPath()];

    $diff = DifferentialDiff::newFromRawChanges(array($change));
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    $this->renderingReference = $drequest->getPath().';'.$drequest->getCommit();

    return $changeset;
  }

  private function buildContentFuture($spec) {
    if (!$spec) {
      return null;
    }

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($ref, $rev) = $spec;
    return $repository->getRemoteCommandFuture(
      'cat %s%s@%d',
      $repository->getRemoteURI(),
      $ref,
      $rev);
  }

}
