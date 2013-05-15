<?php

final class DiffusionGitExpandShortNameQuery
extends DiffusionExpandShortNameQuery {

  protected function executeQuery() {
    $repository = $this->getRepository();
    $commit = $this->getCommit();

    $future = $repository->getLocalCommandFuture(
      'cat-file --batch');
    $future->write($commit);
    list($stdout) = $future->resolvex();

    list($hash, $type) = explode(' ', $stdout);
    if ($type == 'missing') {
      throw new DiffusionExpandCommitQueryException(
        DiffusionExpandCommitQueryException::CODE_MISSING,
        "Bad commit '{$this->commit}'.");
    }

    switch ($type) {
      case 'tag':
        $this->commitType = 'tag';

        $matches = null;
        $ok = preg_match(
          '/^object ([a-f0-9]+)$.*?\n\n(.*)$/sm',
          $stdout,
          $matches);
        if (!$ok) {
          throw new DiffusionExpandCommitQueryException(
            DiffusionExpandCommitQueryException::CODE_UNPARSEABLE,
            "Unparseable output from cat-file: {$stdout}");
        }

        $hash = $matches[1];
        $this->tagContent = trim($matches[2]);
        break;
      case 'commit':
        break;
      default:
        throw new DiffusionExpandCommitQueryException(
          DiffusionExpandCommitQueryException::CODE_INVALID,
          "The reference '{$this->commit}' does not name a valid ".
          'commit or a tag in this repository.');
        break;
    }

    $this->commit = $hash;
  }
}
