<?php

/**
 * Resolves references (like short commit names, branch names, tag names, etc.)
 * into canonical, stable commit identifiers. This query works for all
 * repository types.
 */
final class DiffusionLowLevelResolveRefsQuery
  extends DiffusionLowLevelQuery {

  private $refs;

  public function withRefs(array $refs) {
    $this->refs = $refs;
    return $this;
  }

  public function executeQuery() {
    if (!$this->refs) {
      return array();
    }

    switch ($this->getRepository()->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $result = $this->resolveGitRefs();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $result = $this->resolveMercurialRefs();
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $result = $this->resolveSubversionRefs();
        break;
      default:
        throw new Exception('Unsupported repository type!');
    }

    return $result;
  }

  private function resolveGitRefs() {
    $repository = $this->getRepository();

    $future = $repository->getLocalCommandFuture('cat-file --batch-check');
    $future->write(implode("\n", $this->refs));
    list($stdout) = $future->resolvex();

    $lines = explode("\n", rtrim($stdout, "\n"));
    if (count($lines) !== count($this->refs)) {
      throw new Exception('Unexpected line count from `git cat-file`!');
    }

    $hits = array();
    $tags = array();

    $lines = array_combine($this->refs, $lines);
    foreach ($lines as $ref => $line) {
      $parts = explode(' ', $line);
      if (count($parts) < 2) {
        throw new Exception("Failed to parse `git cat-file` output: {$line}");
      }
      list($identifier, $type) = $parts;

      if ($type == 'missing') {
        // This is either an ambiguous reference which resolves to several
        // objects, or an invalid reference. For now, always treat it as
        // invalid. It would be nice to resolve all possibilities for
        // ambiguous references at some point, although the strategy for doing
        // so isn't clear to me.
        continue;
      }

      switch ($type) {
        case 'commit':
          break;
        case 'tag':
          $tags[] = $identifier;
          break;
        default:
          throw new Exception(
            "Unexpected object type from `git cat-file`: {$line}");
      }

      $hits[] = array(
        'ref' => $ref,
        'type' => $type,
        'identifier' => $identifier,
      );
    }

    $tag_map = array();
    if ($tags) {
      // If some of the refs were tags, just load every tag in order to figure
      // out which commits they map to. This might be somewhat inefficient in
      // repositories with a huge number of tags.
      $tag_refs = id(new DiffusionLowLevelGitRefQuery())
        ->setRepository($repository)
        ->withIsTag(true)
        ->executeQuery();
      foreach ($tag_refs as $tag_ref) {
        $tag_map[$tag_ref->getShortName()] = $tag_ref->getCommitIdentifier();
      }
    }

    $results = array();
    foreach ($hits as $hit) {
      $type = $hit['type'];
      $ref = $hit['ref'];

      $alternate = null;
      if ($type == 'tag') {
        $alternate = $identifier;
        $identifier = idx($tag_map, $ref);
        if (!$identifier) {
          throw new Exception("Failed to look up tag '{$ref}'!");
        }
      }

      $result = array(
        'type' => $type,
        'identifier' => $identifier,
      );

      if ($alternate !== null) {
        $result['alternate'] = $alternate;
      }

      $results[$ref][] = $result;
    }

    return $results;
  }

  private function resolveMercurialRefs() {
    $repository = $this->getRepository();

    $futures = array();
    foreach ($this->refs as $ref) {
      // TODO: There was a note about `--rev 'a b'` not working for branches
      // with spaces in their names in older code, but I suspect this was
      // misidentified and resulted from the branch name being interpeted as
      // a revset. Use hgsprintf() to avoid that. If this doesn't break for a
      // bit, remove this comment. Otherwise, consider `-b %s --limit 1`.

      $futures[$ref] = $repository->getLocalCommandFuture(
        'log --template=%s --rev %s',
        '{node}',
        hgsprintf('%s', $ref));
    }

    $results = array();
    foreach (Futures($futures) as $ref => $future) {
      try {
        list($stdout) = $future->resolvex();
      } catch (CommandException $ex) {
        if (preg_match('/ambiguous identifier/', $ex->getStdErr())) {
          // This indicates that the ref ambiguously matched several things.
          // Eventually, it would be nice to return all of them, but it is
          // unclear how to best do that. For now, treat it as a miss instead.
          continue;
        }
        throw $ex;
      }

      // It doesn't look like we can figure out the type (commit/branch/rev)
      // from this output very easily. For now, just call everything a commit.
      $type = 'commit';

      $results[$ref][] = array(
        'type' => $type,
        'identifier' => trim($stdout),
      );
    }

    return $results;
  }

  private function resolveSubversionRefs() {
    $repository = $this->getRepository();

    $max_commit = id(new PhabricatorRepositoryCommit())
      ->loadOneWhere(
        'repositoryID = %d ORDER BY epoch DESC, id DESC LIMIT 1',
        $repository->getID());
    if (!$max_commit) {
      // This repository is empty or hasn't parsed yet, so none of the refs are
      // going to resolve.
      return array();
    }

    $max_commit_id = (int)$max_commit->getCommitIdentifier();

    $results = array();
    foreach ($this->refs as $ref) {
      if ($ref == 'HEAD') {
        // Resolve "HEAD" to mean "the most recent commit".
        $results[$ref][] = array(
          'type' => 'commit',
          'identifier' => $max_commit_id,
        );
        continue;
      }

      if (!preg_match('/^\d+$/', $ref)) {
        // This ref is non-numeric, so it doesn't resolve to anything.
        continue;
      }

      // Resolve other commits if we can deduce their existence.

      // TODO: When we import only part of a repository, we won't necessarily
      // have all of the smaller commits. Should we fail to resolve them here
      // for repositories with a subpath? It might let us simplify other things
      // elsewhere.
      if ((int)$ref <= $max_commit_id) {
        $results[$ref][] = array(
          'type' => 'commit',
          'identifier' => (int)$ref,
        );
      }
    }

    return $results;
  }

}
