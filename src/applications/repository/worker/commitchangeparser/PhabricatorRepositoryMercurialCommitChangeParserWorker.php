<?php

final class PhabricatorRepositoryMercurialCommitChangeParserWorker
  extends PhabricatorRepositoryCommitChangeParserWorker {

  protected function parseCommitChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    list($stdout) = $repository->execxLocalCommand(
      'status -C --change %s',
      $commit->getCommitIdentifier());
    $status = ArcanistMercurialParser::parseMercurialStatusDetails($stdout);

    $common_attributes = array(
      'repositoryID'    => $repository->getID(),
      'commitID'        => $commit->getID(),
      'commitSequence'  => $commit->getEpoch(),
    );

    $changes = array();

    // Like Git, Mercurial doesn't track directories directly. We need to infer
    // directory creation and removal by observing file creation and removal
    // and testing if the directories in question are previously empty (thus,
    // created) or subsequently empty (thus, removed).
    $maybe_new_directories = array();
    $maybe_del_directories = array();
    $all_directories = array();

    // Parse the basic information from "hg status", which shows files that
    // were directly affected by the change.
    foreach ($status as $path => $path_info) {
      $path = '/'.$path;
      $flags = $path_info['flags'];
      $change_target = $path_info['from'] ? '/'.$path_info['from'] : null;

      $changes[$path] = array(
        'path'            => $path,
        'isDirect'        => true,

        'targetPath'      => $change_target,
        'targetCommitID'  => $change_target ? $commit->getID() : null,

        // We're going to fill these in shortly.
        'changeType'      => null,
        'fileType'        => null,

        'flags'           => $flags,
      ) + $common_attributes;

      if ($flags & ArcanistRepositoryAPI::FLAG_ADDED) {
        $maybe_new_directories[] = dirname($path);
      } else if ($flags & ArcanistRepositoryAPI::FLAG_DELETED) {
        $maybe_del_directories[] = dirname($path);
      }
      $all_directories[] = dirname($path);
    }

    // Add change information for each source path which doesn't appear in the
    // status. These files were copied, but were not modified. We also know they
    // must exist.
    foreach ($changes as $path => $change) {
      $from = $change['targetPath'];
      if ($from && empty($changes[$from])) {
        $changes[$from] = array(
          'path'            => $from,
          'isDirect'        => false,

          'targetPath'      => null,
          'targetCommitID'  => null,

          'changeType'      => DifferentialChangeType::TYPE_COPY_AWAY,
          'fileType'        => null,

          'flags'           => 0,
        ) + $common_attributes;
      }
    }

    $away = array();
    foreach ($changes as $path => $change) {
      $target_path = $change['targetPath'];
      if ($target_path) {
        $away[$target_path][] = $path;
      }
    }

    // Now that we have all the direct changes, figure out change types.
    foreach ($changes as $path => $change) {
      $flags = $change['flags'];
      $from = $change['targetPath'];
      if ($from) {
        $target = $changes[$from];
      } else {
        $target = null;
      }

      if ($flags & ArcanistRepositoryAPI::FLAG_ADDED) {
        if ($target) {
          if ($target['flags'] & ArcanistRepositoryAPI::FLAG_DELETED) {
            $change_type = DifferentialChangeType::TYPE_MOVE_HERE;
          } else {
            $change_type = DifferentialChangeType::TYPE_COPY_HERE;
          }
        } else {
          $change_type = DifferentialChangeType::TYPE_ADD;
        }
      } else if ($flags & ArcanistRepositoryAPI::FLAG_DELETED) {
        if (isset($away[$path])) {
          if (count($away[$path]) > 1) {
            $change_type = DifferentialChangeType::TYPE_MULTICOPY;
          } else {
            $change_type = DifferentialChangeType::TYPE_MOVE_AWAY;
          }
        } else {
          $change_type = DifferentialChangeType::TYPE_DELETE;
        }
      } else {
        if (isset($away[$path])) {
          $change_type = DifferentialChangeType::TYPE_COPY_AWAY;
        } else {
          $change_type = DifferentialChangeType::TYPE_CHANGE;
        }
      }

      $changes[$path]['changeType'] = $change_type;
    }

    // Go through all the affected directories and identify any which were
    // actually added or deleted.
    $dir_status = array();

    foreach ($maybe_del_directories as $dir) {
      $exists = false;
      foreach (DiffusionPathIDQuery::expandPathToRoot($dir) as $path) {
        if (isset($dir_status[$path])) {
          break;
        }

        // If we know some child exists, we know this path exists. If we don't
        // know that a child exists, test if this directory still exists.
        if (!$exists) {
          $exists = $this->mercurialPathExists(
            $repository,
            $path,
            $commit->getCommitIdentifier());
        }

        if ($exists) {
          $dir_status[$path] = DifferentialChangeType::TYPE_CHILD;
        } else {
          $dir_status[$path] = DifferentialChangeType::TYPE_DELETE;
        }
      }
    }

    list($stdout) = $repository->execxLocalCommand(
      'parents --rev %s --style default',
      $commit->getCommitIdentifier());
    $parents = ArcanistMercurialParser::parseMercurialLog($stdout);
    $parent = reset($parents);
    if ($parent) {
      // TODO: We should expand this to a full 40-character hash using "hg id".
      $parent = $parent['rev'];
    }
    foreach ($maybe_new_directories as $dir) {
      $exists = false;
      foreach (DiffusionPathIDQuery::expandPathToRoot($dir) as $path) {
        if (isset($dir_status[$path])) {
          break;
        }
        if (!$exists) {
          if ($parent) {
            $exists = $this->mercurialPathExists($repository, $path, $parent);
          } else {
            $exists = false;
          }
        }
        if ($exists) {
          $dir_status[$path] = DifferentialChangeType::TYPE_CHILD;
        } else {
          $dir_status[$path] = DifferentialChangeType::TYPE_ADD;
        }
      }
    }

    foreach ($all_directories as $dir) {
      foreach (DiffusionPathIDQuery::expandPathToRoot($dir) as $path) {
        if (isset($dir_status[$path])) {
          break;
        }
        $dir_status[$path] = DifferentialChangeType::TYPE_CHILD;
      }
    }

    // Merge all the directory statuses into the path statuses.
    foreach ($dir_status as $path => $status) {
      if (isset($changes[$path])) {
        // TODO: The UI probably doesn't handle any of these cases with
        // terrible elegance, but they are exceedingly rare.

        $existing_type = $changes[$path]['changeType'];
        if ($existing_type == DifferentialChangeType::TYPE_DELETE) {
          // This change removes a file, replaces it with a directory, and then
          // adds children of that directory. Mark it as a "change" instead,
          // and make the type a directory.
          $changes[$path]['fileType'] = DifferentialChangeType::FILE_DIRECTORY;
          $changes[$path]['changeType'] = DifferentialChangeType::TYPE_CHANGE;
        } else if ($existing_type == DifferentialChangeType::TYPE_MOVE_AWAY ||
                   $existing_type == DifferentialChangeType::TYPE_MULTICOPY) {
          // This change moves or copies a file, replaces it with a directory,
          // and then adds children to that directory. Mark it as "copy away"
          // instead of whatever it was, and make the type a directory.
          $changes[$path]['fileType'] = DifferentialChangeType::FILE_DIRECTORY;
          $changes[$path]['changeType']
            = DifferentialChangeType::TYPE_COPY_AWAY;
        } else if ($existing_type == DifferentialChangeType::TYPE_ADD) {
          // This change removes a diretory and replaces it with a file. Mark
          // it as "change" instead of "add".
          $changes[$path]['changeType'] = DifferentialChangeType::TYPE_CHANGE;
        }

        continue;
      }

      $changes[$path] = array(
        'path' => $path,
        'isDirect' => ($status == DifferentialChangeType::TYPE_CHILD)
          ? false
          : true,
        'fileType' => DifferentialChangeType::FILE_DIRECTORY,
        'changeType' => $status,

        'targetPath'      => null,
        'targetCommitID'  => null,
      ) + $common_attributes;
    }

    // TODO: use "hg diff --git" to figure out which files are symlinks.
    foreach ($changes as $path => $change) {
      if (empty($change['fileType'])) {
        $changes[$path]['fileType'] = DifferentialChangeType::FILE_NORMAL;
      }
    }

    $all_paths = array();
    foreach ($changes as $path => $change) {
      $all_paths[$path] = true;
      if ($change['targetPath']) {
        $all_paths[$change['targetPath']] = true;
      }
    }

    $path_map = $this->lookupOrCreatePaths(array_keys($all_paths));

    foreach ($changes as $key => $change) {
      $changes[$key]['pathID'] = $path_map[$change['path']];
      if ($change['targetPath']) {
        $changes[$key]['targetPathID'] = $path_map[$change['targetPath']];
      } else {
        $changes[$key]['targetPathID'] = null;
      }
    }

    $results = array();
    foreach ($changes as $change) {
      $result = id(new PhabricatorRepositoryParsedChange())
        ->setPathID($change['pathID'])
        ->setTargetPathID($change['targetPathID'])
        ->setTargetCommitID($change['targetCommitID'])
        ->setChangeType($change['changeType'])
        ->setFileType($change['fileType'])
        ->setIsDirect($change['isDirect'])
        ->setCommitSequence($change['commitSequence']);

      $results[] = $result;
    }

    return $results;
  }

  private function mercurialPathExists(
    PhabricatorRepository $repository,
    $path,
    $rev) {

    if ($path == '/') {
      return true;
    }

    // NOTE: For directories, this grabs the entire directory contents, but
    // we don't have any more surgical approach available to us in Mercurial.
    // We can't use "log" because it doesn't have enough information for us
    // to figure out when a directory is deleted by a change.
    list($err) = $repository->execLocalCommand(
      'cat --rev %s -- %s > /dev/null',
      $rev,
      $path);

    if ($err) {
      return false;
    } else {
      return true;
    }
  }

}
