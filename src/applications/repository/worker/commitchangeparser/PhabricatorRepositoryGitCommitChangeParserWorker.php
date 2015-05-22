<?php

final class PhabricatorRepositoryGitCommitChangeParserWorker
  extends PhabricatorRepositoryCommitChangeParserWorker {

  protected function parseCommitChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    // Check if the commit has parents. We're testing to see whether it is the
    // first commit in history (in which case we must use "git log") or some
    // other commit (in which case we can use "git diff"). We'd rather use
    // "git diff" because it has the right behavior for merge commits, but
    // it requires the commit to have a parent that we can diff against. The
    // first commit doesn't, so "commit^" is not a valid ref.
    list($parents) = $repository->execxLocalCommand(
      'log -n1 --format=%s %s',
      '%P',
      $commit->getCommitIdentifier());

    $use_log = !strlen(trim($parents));
    if ($use_log) {
      // This is the first commit so we need to use "log". We know it's not a
      // merge commit because it couldn't be merging anything, so this is safe.

      // NOTE: "--pretty=format: " is to disable diff output, we only want the
      // part we get from "--raw".
      list($raw) = $repository->execxLocalCommand(
        'log -n1 -M -C -B --find-copies-harder --raw -t '.
          '--pretty=format: --abbrev=40 %s',
        $commit->getCommitIdentifier());
    } else {
      // Otherwise, we can use "diff", which will give us output for merges.
      // We diff against the first parent, as this is generally the expectation
      // and results in sensible behavior.
      list($raw) = $repository->execxLocalCommand(
        'diff -n1 -M -C -B --find-copies-harder --raw -t '.
          '--abbrev=40 %s^1 %s',
        $commit->getCommitIdentifier(),
        $commit->getCommitIdentifier());
    }

    $changes = array();
    $move_away = array();
    $copy_away = array();
    $lines = explode("\n", $raw);
    foreach ($lines as $line) {
      if (!strlen(trim($line))) {
        continue;
      }
      list($old_mode, $new_mode,
           $old_hash, $new_hash,
           $more_stuff) = preg_split('/ +/', $line, 5);

      // We may only have two pieces here.
      list($action, $src_path, $dst_path) = array_merge(
        explode("\t", $more_stuff),
        array(null));

      // Normalize the paths for consistency with the SVN workflow.
      $src_path = '/'.$src_path;
      if ($dst_path) {
        $dst_path = '/'.$dst_path;
      }

      $old_mode = intval($old_mode, 8);
      $new_mode = intval($new_mode, 8);

      switch ($new_mode & 0160000) {
        case 0160000:
          $file_type = DifferentialChangeType::FILE_SUBMODULE;
          break;
        case 0120000:
          $file_type = DifferentialChangeType::FILE_SYMLINK;
          break;
        case 0040000:
          $file_type = DifferentialChangeType::FILE_DIRECTORY;
          break;
        default:
          $file_type = DifferentialChangeType::FILE_NORMAL;
          break;
      }


      // TODO: We can detect binary changes as git does, through a combination
      // of running 'git check-attr' for stuff like 'binary', 'merge' or 'diff',
      // and by falling back to inspecting the first 8,000 characters of the
      // buffer for null bytes (this is seriously git's algorithm, see
      // buffer_is_binary() in xdiff-interface.c).

      $change_type = null;
      $change_path = $src_path;
      $change_target = null;
      $is_direct = true;

      switch ($action[0]) {
        case 'A':
          $change_type = DifferentialChangeType::TYPE_ADD;
          break;
        case 'D':
          $change_type = DifferentialChangeType::TYPE_DELETE;
          break;
        case 'C':
          $change_type = DifferentialChangeType::TYPE_COPY_HERE;
          $change_path = $dst_path;
          $change_target = $src_path;
          $copy_away[$change_target][] = $change_path;
          break;
        case 'R':
          $change_type = DifferentialChangeType::TYPE_MOVE_HERE;
          $change_path = $dst_path;
          $change_target = $src_path;
          $move_away[$change_target][] = $change_path;
          break;
        case 'T':
          // Type of the file changed, fall through and treat it as a
          // modification. Not 100% sure this is the right thing to do but it
          // seems reasonable.
        case 'M':
          if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
            $change_type = DifferentialChangeType::TYPE_CHILD;
            $is_direct = false;
          } else {
            $change_type = DifferentialChangeType::TYPE_CHANGE;
          }
          break;
        // NOTE: "U" (unmerged) and "X" (unknown) statuses are also possible
        // in theory but shouldn't appear here.
        default:
          throw new Exception(pht("Failed to parse line '%s'.", $line));
      }

      $changes[$change_path] = array(
        'repositoryID'      => $repository->getID(),
        'commitID'          => $commit->getID(),

        'path'              => $change_path,
        'changeType'        => $change_type,
        'fileType'          => $file_type,
        'isDirect'          => $is_direct,
        'commitSequence'    => $commit->getEpoch(),

        'targetPath'        => $change_target,
        'targetCommitID'    => $change_target ? $commit->getID() : null,
      );
    }

    // Add a change to '/' since git doesn't mention it.
    $changes['/'] = array(
      'repositoryID'      => $repository->getID(),
      'commitID'          => $commit->getID(),

      'path'              => '/',
      'changeType'        => DifferentialChangeType::TYPE_CHILD,
      'fileType'          => DifferentialChangeType::FILE_DIRECTORY,
      'isDirect'          => false,
      'commitSequence'    => $commit->getEpoch(),

      'targetPath'        => null,
      'targetCommitID'    => null,
    );

    foreach ($copy_away as $change_path => $destinations) {
      if (isset($move_away[$change_path])) {
        $change_type = DifferentialChangeType::TYPE_MULTICOPY;
        $is_direct = true;
        unset($move_away[$change_path]);
      } else {
        $change_type = DifferentialChangeType::TYPE_COPY_AWAY;

        // This change is direct if we picked up a modification above (i.e.,
        // the original copy source was also edited). Otherwise the original
        // wasn't touched, so leave it as an indirect change.
        $is_direct = isset($changes[$change_path]);
      }

      $reference = $changes[reset($destinations)];

      $changes[$change_path] = array(
        'repositoryID'      => $repository->getID(),
        'commitID'          => $commit->getID(),

        'path'              => $change_path,
        'changeType'        => $change_type,
        'fileType'          => $reference['fileType'],
        'isDirect'          => $is_direct,
        'commitSequence'    => $commit->getEpoch(),

        'targetPath'        => null,
        'targetCommitID'    => null,
      );
    }

    foreach ($move_away as $change_path => $destinations) {
      $reference = $changes[reset($destinations)];

      $changes[$change_path] = array(
        'repositoryID'      => $repository->getID(),
        'commitID'          => $commit->getID(),

        'path'              => $change_path,
        'changeType'        => DifferentialChangeType::TYPE_MOVE_AWAY,
        'fileType'          => $reference['fileType'],
        'isDirect'          => true,
        'commitSequence'    => $commit->getEpoch(),

        'targetPath'        => null,
        'targetCommitID'    => null,
      );
    }

    $paths = array();
    foreach ($changes as $change) {
      $paths[$change['path']] = true;
      if ($change['targetPath']) {
        $paths[$change['targetPath']] = true;
      }
    }

    $path_map = $this->lookupOrCreatePaths(array_keys($paths));

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

}
