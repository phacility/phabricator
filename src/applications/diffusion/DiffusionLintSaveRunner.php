<?php

final class DiffusionLintSaveRunner extends Phobject {
  private $arc = 'arc';
  private $severity = ArcanistLintSeverity::SEVERITY_ADVICE;
  private $all = false;
  private $chunkSize = 256;
  private $needsBlame = false;

  private $svnRoot;
  private $lintCommit;
  private $branch;
  private $conn;
  private $deletes = array();
  private $inserts = array();
  private $blame = array();


  public function setArc($path) {
    $this->arc = $path;
    return $this;
  }

  public function setSeverity($string) {
    $this->severity = $string;
    return $this;
  }

  public function setAll($bool) {
    $this->all = $bool;
    return $this;
  }

  public function setChunkSize($number) {
    $this->chunkSize = $number;
    return $this;
  }

  public function setNeedsBlame($boolean) {
    $this->needsBlame = $boolean;
    return $this;
  }


  public function run($dir) {
    $working_copy = ArcanistWorkingCopyIdentity::newFromPath($dir);
    $configuration_manager = new ArcanistConfigurationManager();
    $configuration_manager->setWorkingCopyIdentity($working_copy);
    $api = ArcanistRepositoryAPI::newAPIFromConfigurationManager(
      $configuration_manager);

    $this->svnRoot = id(new PhutilURI($api->getSourceControlPath()))->getPath();
    if ($api instanceof ArcanistGitAPI) {
      $svn_fetch = $api->getGitConfig('svn-remote.svn.fetch');
      list($this->svnRoot) = explode(':', $svn_fetch);
      if ($this->svnRoot != '') {
        $this->svnRoot = '/'.$this->svnRoot;
      }
    }

    $callsign = $configuration_manager->getConfigFromAnySource(
      'repository.callsign');
    $uuid = $api->getRepositoryUUID();
    $remote_uri = $api->getRemoteURI();

    $repository_query = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser());

    if ($callsign) {
      $repository_query->withCallsigns(array($callsign));
    } else if ($uuid) {
      $repository_query->withUUIDs(array($uuid));
    } else if ($remote_uri) {
      $repository_query->withURIs(array($remote_uri));
    }

    $repository = $repository_query->executeOne();
    $branch_name = $api->getBranchName();

    if (!$repository) {
      throw new Exception(pht('No repository was found.'));
    }

    $this->branch = PhabricatorRepositoryBranch::loadOrCreateBranch(
      $repository->getID(),
      $branch_name);
    $this->conn = $this->branch->establishConnection('w');

    $this->lintCommit = null;
    if (!$this->all) {
      $this->lintCommit = $this->branch->getLintCommit();
    }

    if ($this->lintCommit) {
      try {
        $commit = $this->lintCommit;
        if ($this->svnRoot) {
          $commit = $api->getCanonicalRevisionName('@'.$commit);
        }
        $all_files = $api->getChangedFiles($commit);
      } catch (ArcanistCapabilityNotSupportedException $ex) {
        $this->lintCommit = null;
      }
    }


    if (!$this->lintCommit) {
      $where = ($this->svnRoot
        ? qsprintf($this->conn, 'AND path LIKE %>', $this->svnRoot.'/')
        : '');
      queryfx(
        $this->conn,
        'DELETE FROM %T WHERE branchID = %d %Q',
        PhabricatorRepository::TABLE_LINTMESSAGE,
        $this->branch->getID(),
        $where);
      $all_files = $api->getAllFiles();
    }

    $count = 0;

    $files = array();
    foreach ($all_files as $file => $val) {
      $count++;
      if (!$this->lintCommit) {
        $file = $val;
      } else {
        $this->deletes[] = $this->svnRoot.'/'.$file;
        if ($val & ArcanistRepositoryAPI::FLAG_DELETED) {
          continue;
        }
      }
      $files[$file] = $file;

      if (count($files) >= $this->chunkSize) {
        $this->runArcLint($files);
        $files = array();
      }
    }

    $this->runArcLint($files);
    $this->saveLintMessages();

    $this->lintCommit = $api->getUnderlyingWorkingCopyRevision();
    $this->branch->setLintCommit($this->lintCommit);
    $this->branch->save();

    if ($this->blame) {
      $this->blameAuthors();
      $this->blame = array();
    }

    return $count;
  }


  private function runArcLint(array $files) {
    if (!$files) {
      return;
    }

    echo '.';
    try {
      $future = new ExecFuture(
        '%C lint --severity %s --output json %Ls',
        $this->arc,
        $this->severity,
        $files);

      foreach (new LinesOfALargeExecFuture($future) as $json) {
        $paths = null;
        try {
          $paths = phutil_json_decode($json);
        } catch (PhutilJSONParserException $ex) {
          fprintf(STDERR, pht('Invalid JSON: %s', $json)."\n");
          continue;
        }

        foreach ($paths as $path => $messages) {
          if (!isset($files[$path])) {
            continue;
          }

          foreach ($messages as $message) {
            $line = idx($message, 'line', 0);

            $this->inserts[] = qsprintf(
              $this->conn,
              '(%d, %s, %d, %s, %s, %s, %s)',
              $this->branch->getID(),
              $this->svnRoot.'/'.$path,
              $line,
              idx($message, 'code', ''),
              idx($message, 'severity', ''),
              idx($message, 'name', ''),
              idx($message, 'description', ''));

            if ($line && $this->needsBlame) {
              $this->blame[$path][$line] = true;
            }
          }

          if (count($this->deletes) >= 1024 || count($this->inserts) >= 256) {
            $this->saveLintMessages();
          }
        }
      }

    } catch (Exception $ex) {
      fprintf(STDERR, $ex->getMessage()."\n");
    }
  }


  private function saveLintMessages() {
    $this->conn->openTransaction();

    foreach (array_chunk($this->deletes, 1024) as $paths) {
      queryfx(
        $this->conn,
        'DELETE FROM %T WHERE branchID = %d AND path IN (%Ls)',
        PhabricatorRepository::TABLE_LINTMESSAGE,
        $this->branch->getID(),
        $paths);
    }

    foreach (array_chunk($this->inserts, 256) as $values) {
      queryfx(
        $this->conn,
        'INSERT INTO %T
          (branchID, path, line, code, severity, name, description)
          VALUES %Q',
        PhabricatorRepository::TABLE_LINTMESSAGE,
        implode(', ', $values));
    }

    $this->conn->saveTransaction();

    $this->deletes = array();
    $this->inserts = array();
  }


  private function blameAuthors() {
    $repository = id(new PhabricatorRepositoryQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->branch->getRepositoryID()))
      ->executeOne();

    $queries = array();
    $futures = array();
    foreach ($this->blame as $path => $lines) {
      $drequest = DiffusionRequest::newFromDictionary(array(
        'user' => PhabricatorUser::getOmnipotentUser(),
        'repository' => $repository,
        'branch' => $this->branch->getName(),
        'path' => $path,
        'commit' => $this->lintCommit,
      ));

      // TODO: Restore blame information / generally fix this workflow.

      $query = DiffusionFileContentQuery::newFromDiffusionRequest($drequest);
      $queries[$path] = $query;
      $futures[$path] = $query->getFileContentFuture();
    }

    $authors = array();

    $futures = id(new FutureIterator($futures))
      ->limit(8);
    foreach ($futures as $path => $future) {
      $queries[$path]->loadFileContentFromFuture($future);
      list(, $rev_list, $blame_dict) = $queries[$path]->getBlameData();
      foreach (array_keys($this->blame[$path]) as $line) {
        $commit_identifier = $rev_list[$line - 1];
        $author = idx($blame_dict[$commit_identifier], 'authorPHID');
        if ($author) {
          $authors[$author][$path][] = $line;
        }
      }
    }

    if ($authors) {
      $this->conn->openTransaction();

      foreach ($authors as $author => $paths) {
        $where = array();
        foreach ($paths as $path => $lines) {
          $where[] = qsprintf(
            $this->conn,
            '(path = %s AND line IN (%Ld))',
            $this->svnRoot.'/'.$path,
            $lines);
        }
        queryfx(
          $this->conn,
          'UPDATE %T SET authorPHID = %s WHERE %Q',
          PhabricatorRepository::TABLE_LINTMESSAGE,
          $author,
          implode(' OR ', $where));
      }

      $this->conn->saveTransaction();
    }
  }

}
