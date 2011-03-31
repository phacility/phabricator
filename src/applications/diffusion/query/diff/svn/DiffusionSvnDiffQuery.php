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

final class DiffusionSvnDiffQuery extends DiffusionDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();

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
      $futures[$key] = $future->resolvex();
    }

    $old_data = idx($futures, 'old', '');
    $new_data = idx($futures, 'new', '');

    $old_tmp = new TempFile();
    $new_tmp = new TempFile();

    Filesystem::writeFile($old_tmp, $old_data);
    Filesystem::writeFile($new_tmp, $new_data);

    list($err, $raw_diff) = exec_manual(
      'diff -L %s -L %s -U65535 %s %s',
      nonempty($old_name, '/dev/universe').' 9999-99-99',
      nonempty($new_name, '/dev/universe').' 9999-99-99',
      $old_tmp,
      $new_tmp);

    $parser = new ArcanistDiffParser();
    $parser->setDetectBinaryFiles(true);

    $change = $parser->parseDiffusionPathChangesAndRawDiff(
      $drequest->getPath(),
      $path_changes,
      $raw_diff);

    $diff = DifferentialDiff::newFromRawChanges(array($change));
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    $reference = $drequest->getPath().';'.$drequest->getCommit();
    $changeset->setRenderingReference($reference);

    return $changeset;
  }

  private function buildContentFuture($spec) {
    if (!$spec) {
      return null;
    }

    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    list($ref, $rev) = $spec;
    return new ExecFuture(
      'svn --non-interactive cat %s%s@%d',
      $repository->getDetail('remote-uri'),
      $ref,
      $rev);
  }

}
