<?php

final class PhabricatorRepositorySvnCommitChangeParserWorker
  extends PhabricatorRepositoryCommitChangeParserWorker {

  protected function parseCommitChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    // PREAMBLE: This class is absurdly complicated because it is very difficult
    // to get the information we need out of SVN. The actual data we need is:
    //
    //  1. Recursively, what were the affected paths?
    //  2. For each affected path, is it a file or a directory?
    //  3. How was each path affected (e.g. add, delete, move, copy)?
    //
    // We spend nearly all of our effort figuring out (1) and (2) because
    // "svn log" is not recursive and does not give us file/directory
    // information (that is, it will report a directory move as a single move,
    // even if many thousands of paths are affected).
    //
    // Instead, we have to "svn ls -R" the location of each path in its previous
    // life to figure out whether it is a file or a directory and exactly which
    // recursive paths were affected if it was moved or copied. This is very
    // complicated and has many special cases.

    $uri = $repository->getSubversionPathURI();
    $svn_commit = $commit->getCommitIdentifier();

    // Pull the top-level path changes out of "svn log". This is pretty
    // straightforward; just parse the XML log.
    $log = $this->getSVNLogXMLObject($repository, $uri, $svn_commit);

    $entry = $log->logentry[0];

    if (!$entry->paths) {
      // TODO: Explicitly mark this commit as broken elsewhere? This isn't
      // supposed to happen but we have some cases like rE27 and rG935 in the
      // Facebook repositories where things got all clowned up.
      return array();
    }

    $raw_paths = array();
    foreach ($entry->paths->path as $path) {
      $name = trim((string)$path);
      $raw_paths[$name] = array(
        'rawPath'         => $name,
        'rawTargetPath'   => (string)$path['copyfrom-path'],
        'rawChangeType'   => (string)$path['action'],
        'rawTargetCommit' => (string)$path['copyfrom-rev'],
      );
    }

    $copied_or_moved_map = array();
    $deleted_paths = array();
    $add_paths = array();

    foreach ($raw_paths as $path => $raw_info) {
      if ($raw_info['rawTargetPath']) {
        $copied_or_moved_map[$raw_info['rawTargetPath']][] = $raw_info;
      }
      switch ($raw_info['rawChangeType']) {
        case 'D':
          $deleted_paths[$path] = $raw_info;
          break;
        case 'A':
        case 'R':
          $add_paths[$path] = $raw_info;
          break;
      }
    }

    // If a path was deleted, we need to look in the repository history to
    // figure out where the former valid location for it is so we can figure out
    // if it was a directory or not, among other things.
    $lookup_here = array();
    foreach ($raw_paths as $path => $raw_info) {
      if ($raw_info['rawChangeType'] != 'D') {
        continue;
      }

      // If a change copies a directory and then deletes something from it,
      // we need to look at the old location for information about the path, not
      // the new location. This workflow is pretty ridiculous -- so much so that
      // Trac gets it wrong. See Facebook rO6 for an example, if you happen to
      // work at Facebook.
      $parents = $this->expandAllParentPaths($path, $include_self = true);
      foreach ($parents as $parent) {
        if (isset($add_paths[$parent])) {
          $relative_path = substr($path, strlen($parent));
          $lookup_here[$path] = array(
            'rawPath'   => $add_paths[$parent]['rawTargetPath'].$relative_path,
            'rawCommit' => $add_paths[$parent]['rawTargetCommit'],
          );
          continue 2;
        }
      }

      // Otherwise we can just look at the previous revision.
      $lookup_here[$path] = array(
        'rawPath'   => $path,
        'rawCommit' => $svn_commit - 1,
      );
    }

    $lookup = array();
    foreach ($raw_paths as $path => $raw_info) {
      if ($raw_info['rawChangeType'] == 'D') {
        $lookup[$path] = $lookup_here[$path];
      } else {
        // For everything that wasn't deleted, we can just look it up directly.
        $lookup[$path] = array(
          'rawPath'   => $path,
          'rawCommit' => $svn_commit,
        );
      }
    }

    $effects = array();

    $path_file_types = $this->lookupPathFileTypes($repository, $lookup);
    foreach ($raw_paths as $path => $raw_info) {
      if ($raw_info['rawChangeType'] == 'D' &&
          $path_file_types[$path] == DifferentialChangeType::FILE_DIRECTORY) {

        // Bad. Child paths aren't enumerated in "svn log" so we need
        // to go fishing.
        $list = $this->lookupRecursiveFileList(
          $repository,
          $lookup[$path]);

        foreach ($list as $deleted_path => $path_file_type) {
          $deleted_path = rtrim($path.'/'.$deleted_path, '/');
          if (!empty($raw_paths[$deleted_path])) {
            // We somehow learned about this deletion explicitly?
            // TODO: Unclear how this is possible.
            continue;
          }
          $effect_type = DifferentialChangeType::TYPE_DELETE;
          $effect_target_path = null;
          if (isset($copied_or_moved_map[$deleted_path])) {
            $effect_target_path = $path;
            if (count($copied_or_moved_map[$deleted_path]) > 1) {
              $effect_type = DifferentialChangeType::TYPE_MULTICOPY;
            } else {
              $effect_type = DifferentialChangeType::TYPE_MOVE_AWAY;
            }
          }
          $effects[$deleted_path] = array(
            'rawPath'         => $deleted_path,
            'rawTargetPath'   => $effect_target_path,
            'rawTargetCommit' => null,
            'rawDirect'       => true,
            'changeType'      => $effect_type,
            'fileType'        => $path_file_type,
          );
          $deleted_paths[$deleted_path] = $effects[$deleted_path];
        }
      }
    }

    $resolved_types = array();
    $supplemental = array();
    foreach ($raw_paths as $path => $raw_info) {
      if (isset($resolved_types[$path])) {
        $type = $resolved_types[$path];
      } else {
        switch ($raw_info['rawChangeType']) {
          case 'D':
            if (isset($copied_or_moved_map[$path])) {
              if (count($copied_or_moved_map[$path]) > 1) {
                $type = DifferentialChangeType::TYPE_MULTICOPY;
              } else {
                $type = DifferentialChangeType::TYPE_MOVE_AWAY;
              }
            } else {
              $type = DifferentialChangeType::TYPE_DELETE;
            }
            break;
          case 'A':
            $copy_from = $raw_info['rawTargetPath'];
            $copy_rev = $raw_info['rawTargetCommit'];
            if (!strlen($copy_from)) {
              $type = DifferentialChangeType::TYPE_ADD;
            } else {
              if (isset($deleted_paths[$copy_from])) {
                $type = DifferentialChangeType::TYPE_MOVE_HERE;
                $other_type = DifferentialChangeType::TYPE_MOVE_AWAY;
              } else {
                $type = DifferentialChangeType::TYPE_COPY_HERE;
                $other_type = DifferentialChangeType::TYPE_COPY_AWAY;
              }

              $source_file_type = $this->lookupPathFileType(
                $repository,
                $copy_from,
                array(
                  'rawPath'   => $copy_from,
                  'rawCommit' => $copy_rev,
                ));

              if ($source_file_type == DifferentialChangeType::FILE_DELETED) {
                throw new Exception(
                  pht('Something is wrong; source of a copy must exist.'));
              }

              if ($source_file_type != DifferentialChangeType::FILE_DIRECTORY) {
                if (isset($raw_paths[$copy_from]) ||
                    isset($effects[$copy_from])) {
                  break;
                }
                $effects[$copy_from] = array(
                  'rawPath'           => $copy_from,
                  'rawTargetPath'     => null,
                  'rawTargetCommit'   => null,
                  'rawDirect'         => false,

                  'changeType'        => $other_type,
                  'fileType'          => $source_file_type,
                );
              } else {
                // ULTRADISASTER. We've added a directory which was copied
                // or moved from somewhere else. This is the most complex and
                // ridiculous case.

                $list = $this->lookupRecursiveFileList(
                  $repository,
                  array(
                    'rawPath'   => $copy_from,
                    'rawCommit' => $copy_rev,
                  ));

                foreach ($list as $from_path => $from_file_type) {
                  $full_from = rtrim($copy_from.'/'.$from_path, '/');
                  $full_to = rtrim($path.'/'.$from_path, '/');

                  if (empty($raw_paths[$full_to])) {
                    $effects[$full_to] = array(
                      'rawPath'         => $full_to,
                      'rawTargetPath'   => $full_from,
                      'rawTargetCommit' => $copy_rev,
                      'rawDirect'       => true,

                      'changeType'      => $type,
                      'fileType'        => $from_file_type,
                    );
                  } else {
                    // This means we picked the file up explicitly elsewhere.
                    // If the file as modified, SVN will drop the copy
                    // information. We need to restore it.
                    $supplemental[$full_to]['rawTargetPath'] = $full_from;
                    $supplemental[$full_to]['rawTargetCommit'] = $copy_rev;
                    if ($raw_paths[$full_to]['rawChangeType'] == 'M') {
                      $resolved_types[$full_to] = $type;
                    }
                  }

                  if (empty($raw_paths[$full_from]) &&
                      empty($effects[$full_from])) {
                    if ($other_type == DifferentialChangeType::TYPE_COPY_AWAY) {
                      // Add an indirect effect for the copied file, if we
                      // don't already have an entry for it (e.g., a separate
                      // change).
                      $effects[$full_from] = array(
                        'rawPath'         => $full_from,
                        'rawTargetPath'   => null,
                        'rawTargetCommit' => null,
                        'rawDirect'       => false,

                        'changeType'      => $other_type,
                        'fileType'        => $from_file_type,
                      );
                    }
                  }
                }
              }
            }
            break;
          // This is "replaced", caused by "svn rm"-ing a file, putting another
          // in its place, and then "svn add"-ing it. We do not distinguish
          // between this and "M".
          case 'R':
          case 'M':
            if (isset($copied_or_moved_map[$path])) {
              $type = DifferentialChangeType::TYPE_COPY_AWAY;
            } else {
              $type = DifferentialChangeType::TYPE_CHANGE;
            }
            break;
        }
      }
      $resolved_types[$path] = $type;
    }

    foreach ($raw_paths as $path => $raw_info) {
      $raw_paths[$path]['changeType'] = $resolved_types[$path];
      if (isset($supplemental[$path])) {
        foreach ($supplemental[$path] as $key => $value) {
          $raw_paths[$path][$key] = $value;
        }
      }
    }

    foreach ($raw_paths as $path => $raw_info) {
      $effects[$path] = array(
        'rawPath'         => $path,
        'rawTargetPath'   => $raw_info['rawTargetPath'],
        'rawTargetCommit' => $raw_info['rawTargetCommit'],
        'rawDirect'       => true,

        'changeType'      => $raw_info['changeType'],
        'fileType'        => $path_file_types[$path],
      );
    }

    $parents = array();
    foreach ($effects as $path => $effect) {
      foreach ($this->expandAllParentPaths($path) as $parent_path) {
        $parents[$parent_path] = true;
      }
    }
    $parents = array_keys($parents);

    foreach ($parents as $parent) {
      if (isset($effects[$parent])) {
        continue;
      }

      $effects[$parent] = array(
        'rawPath' => $parent,
        'rawTargetPath' => null,
        'rawTargetCommit' => null,
        'rawDirect' => false,

        'changeType' => DifferentialChangeType::TYPE_CHILD,
        'fileType'   => DifferentialChangeType::FILE_DIRECTORY,
      );
    }

    $lookup_paths = array();
    foreach ($effects as $effect) {
      $lookup_paths[$effect['rawPath']] = true;
      if ($effect['rawTargetPath']) {
        $lookup_paths[$effect['rawTargetPath']] = true;
      }
    }
    $lookup_paths = array_keys($lookup_paths);

    $lookup_commits = array();
    foreach ($effects as $effect) {
      if ($effect['rawTargetCommit']) {
        $lookup_commits[$effect['rawTargetCommit']] = true;
      }
    }
    $lookup_commits = array_keys($lookup_commits);

    $path_map = $this->lookupOrCreatePaths($lookup_paths);
    $commit_map = $this->lookupSvnCommits($repository, $lookup_commits);

    $this->writeBrowse($repository, $commit, $effects, $path_map);

    return $this->buildChanges(
      $repository,
      $commit,
      $effects,
      $path_map,
      $commit_map);
  }

  private function buildChanges(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    array $effects,
    array $path_map,
    array $commit_map) {

    $results = array();
    foreach ($effects as $effect) {
      $path_id = $path_map[$effect['rawPath']];

      $target_path_id = null;
      if ($effect['rawTargetPath']) {
        $target_path_id = $path_map[$effect['rawTargetPath']];
      }

      $target_commit_id = null;
      if ($effect['rawTargetCommit']) {
        $target_commit_id = $commit_map[$effect['rawTargetCommit']];
      }

      $result = id(new PhabricatorRepositoryParsedChange())
        ->setPathID($path_id)
        ->setTargetPathID($target_path_id)
        ->setTargetCommitID($target_commit_id)
        ->setChangeType($effect['changeType'])
        ->setFileType($effect['fileType'])
        ->setIsDirect($effect['rawDirect'])
        ->setCommitSequence($commit->getCommitIdentifier());

      $results[] = $result;
    }

    return $results;
  }

  private function writeBrowse(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    array $effects,
    array $path_map) {

    $conn_w = $repository->establishConnection('w');

    $sql = array();
    foreach ($effects as $effect) {
      $type = $effect['changeType'];

      if (!$effect['rawDirect']) {
        if ($type == DifferentialChangeType::TYPE_COPY_AWAY) {
          // Don't write COPY_AWAY to the filesystem table if it isn't a direct
          // event.
          continue;
        }
        if ($type == DifferentialChangeType::TYPE_CHILD) {
          // Don't write CHILD to the filesystem table. Although doing these
          // writes has the nice property of letting you see when a directory's
          // contents were last changed, it explodes the table tremendously
          // and makes Diffusion far slower.
          continue;
        }
      }

      if ($effect['rawPath'] == '/') {
        // Don't write any events on '/' to the filesystem table; in
        // particular, it doesn't have a meaningful parentID.
        continue;
      }

      $existed = !DifferentialChangeType::isDeleteChangeType($type);

      $sql[] = qsprintf(
        $conn_w,
        '(%d, %d, %d, %d, %d, %d)',
        $repository->getID(),
        $path_map[$this->getParentPath($effect['rawPath'])],
        $commit->getCommitIdentifier(),
        $path_map[$effect['rawPath']],
        $existed
          ? 1
          : 0,
        $effect['fileType']);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE repositoryID = %d AND svnCommit = %d',
      PhabricatorRepository::TABLE_FILESYSTEM,
      $repository->getID(),
      $commit->getCommitIdentifier());

    foreach (array_chunk($sql, 512) as $sql_chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T
          (repositoryID, parentID, svnCommit, pathID, existed, fileType)
          VALUES %Q',
        PhabricatorRepository::TABLE_FILESYSTEM,
        implode(', ', $sql_chunk));
    }

  }

  private function lookupSvnCommits(
    PhabricatorRepository $repository,
    array $commits) {

    if (!$commits) {
      return array();
    }

    $commit_table = new PhabricatorRepositoryCommit();
    $commit_data = queryfx_all(
      $commit_table->establishConnection('w'),
      'SELECT id, commitIdentifier FROM %T
        WHERE repositoryID = %d AND commitIdentifier in (%Ls)',
      $commit_table->getTableName(),
      $repository->getID(),
      $commits);

    $commit_map = ipull($commit_data, 'id', 'commitIdentifier');

    $need = array();
    foreach ($commits as $commit) {
      if (empty($commit_map[$commit])) {
        $need[] = $commit;
      }
    }

    // If we are parsing a Subversion repository and have been configured to
    // import only some subdirectory of it, we may find commits which reference
    // other foreign commits outside of the directory (for instance, because of
    // a move or copy). Rather than trying to execute full parses on them, just
    // create stub commits and identify the stubs as foreign commits.
    if ($need) {
      $subpath = $repository->getDetail('svn-subpath');
      if (!$subpath) {
        throw new Exception(
          pht(
            'Missing commits (%s) in a SVN repository which is not '.
            'configured for subdirectory-only parsing!',
            implode(', ', $need)));
      }

      foreach ($need as $foreign_commit) {
        $commit = new PhabricatorRepositoryCommit();
        $commit->setRepositoryID($repository->getID());
        $commit->setCommitIdentifier($foreign_commit);
        $commit->setEpoch(0);
        // Mark this commit as imported so it doesn't prevent the repository
        // from transitioning into the "Imported" state.
        $commit->setImportStatus(PhabricatorRepositoryCommit::IMPORTED_ALL);
        $commit->save();

        $data = new PhabricatorRepositoryCommitData();
        $data->setCommitID($commit->getID());
        $data->setAuthorName('');
        $data->setCommitMessage('');
        $data->setCommitDetails(
          array(
            'foreign-svn-stub'  => true,
            // Denormalize this to make it easier to debug cases where someone
            // did half a parse and then changed the subdirectory or something
            // like that.
            'svn-subpath'       => $subpath,
          ));
        $data->save();
        $commit_map[$foreign_commit] = $commit->getID();
      }
    }

    return $commit_map;
  }

  private function lookupPathFileType(
    PhabricatorRepository $repository,
    $path,
    array $path_info) {

    $result = $this->lookupPathFileTypes(
      $repository,
      array(
        $path => $path_info,
      ));

    return $result[$path];
  }

  private function lookupPathFileTypes(
    PhabricatorRepository $repository,
    array $paths) {

    $result_map = array();
    $repository_uri = $repository->getSubversionPathURI();

    if (isset($paths['/'])) {
      $result_map['/'] = DifferentialChangeType::FILE_DIRECTORY;
      unset($paths['/']);
    }

    $parents = array();
    $path_mapping = array();
    foreach ($paths as $path => $lookup) {
      $parent = dirname($lookup['rawPath']);
      $parent = $repository->getSubversionPathURI(
        $parent,
        $lookup['rawCommit']);

      $parent = escapeshellarg($parent);
      $parents[$parent] = true;
      $path_mapping[$parent][] = dirname($path);
    }

    // Reverse this list so we can pop $path_mapping, as that's more efficient
    // than shifting it. We need to associate these maps positionally because
    // a change can copy the same source path from multiple revisions via
    // "svn cp path@1 a; svn cp path@2 b;" and the XML output gives us no way
    // to distinguish which revision we're looking at except based on its
    // position in the document.
    $all_paths = array_reverse(array_keys($parents));
    foreach (array_chunk($all_paths, 64) as $path_chunk) {
      list($raw_xml) = $repository->execxRemoteCommand(
         '--xml ls %C',
         implode(' ', $path_chunk));
      $xml = new SimpleXMLElement($raw_xml);
      foreach ($xml->list as $list) {
        $list_path = (string)$list['path'];

        // SVN is a big mess. See Facebook rG8 (a revision which adds files
        // with spaces in their names) for an example.
        $list_path = rawurldecode($list_path);

        if ($list_path == $repository_uri) {
          $base = '/';
        } else {
          $base = substr($list_path, strlen($repository_uri));
        }

        $mapping = array_pop($path_mapping);
        foreach ($list->entry as $entry) {
          $val = $this->getFileTypeFromSVNKind($entry['kind']);
          foreach ($mapping as $base_path) {
            // rtrim() causes us to handle top-level directories correctly.
            $key = rtrim($base_path, '/').'/'.$entry->name;
            $result_map[$key] = $val;
          }
        }
      }
    }

    foreach ($paths as $path => $lookup) {
      if (empty($result_map[$path])) {
        $result_map[$path] = DifferentialChangeType::FILE_DELETED;
      }
    }

    return $result_map;
  }

  private function getFileTypeFromSVNKind($kind) {
    $kind = (string)$kind;
    switch ($kind) {
      case 'dir':   return DifferentialChangeType::FILE_DIRECTORY;
      case 'file':  return DifferentialChangeType::FILE_NORMAL;
      default:
        throw new Exception(pht("Unknown SVN file kind '%s'.", $kind));
    }
  }

  private function lookupRecursiveFileList(
    PhabricatorRepository $repository,
    array $info) {

    $path = $info['rawPath'];
    $rev  = $info['rawCommit'];

    $path_uri = $repository->getSubversionPathURI($path, $rev);
    $hashkey = md5($path_uri);

    // This method is quite horrible. The underlying challenge is that some
    // commits in the Facebook repository are enormous, taking multiple hours
    // to 'ls -R' out of the repository and producing XML files >1GB in size.

    // If we try to SimpleXML them, the object exhausts available memory on a
    // 64G machine. Instead, cache the XML output and then parse it line by line
    // to limit space requirements.

    $cache_loc = sys_get_temp_dir().'/diffusion.'.$hashkey.'.svnls';
    if (!Filesystem::pathExists($cache_loc)) {
      $tmp = new TempFile();
      $repository->execxRemoteCommand(
        '--xml ls -R %s > %s',
        $path_uri,
        $tmp);
      execx(
        'mv %s %s',
        $tmp,
        $cache_loc);
    }

    $map = $this->parseRecursiveListFileData($cache_loc);
    Filesystem::remove($cache_loc);

    return $map;
  }

  private function parseRecursiveListFileData($file_path) {
    $map   = array();
    $mode  = 'xml';
    $done  = false;
    $entry = null;
    foreach (new LinesOfALargeFile($file_path) as $lno => $line) {
      switch ($mode) {
        case 'entry':
          if ($line == '</entry>') {
            $entry = implode('', $entry);
            $pattern = '@^\s+kind="(file|dir)">'.
                       '<name>(.*?)</name>'.
                       '(<size>(.*?)</size>)?@';
            $matches = null;
            if (!preg_match($pattern, $entry, $matches)) {
              throw new Exception(pht('Unable to parse entry!'));
            }
            $map[html_entity_decode($matches[2])] =
              $this->getFileTypeFromSVNKind($matches[1]);
            $mode = 'entry-or-end';
          } else {
            $entry[] = $line;
          }
          break;
        case 'entry-or-end':
          if ($line == '</list>') {
            $done = true;
            break 2;
          } else if ($line == '<entry') {
            $mode = 'entry';
            $entry = array();
          } else {
            throw new Exception(
              pht(
                'Expected %s or %s, got %s.',
                '</list>',
                '<entry',
                $line));
          }
          break;
        case 'xml':
          $expect = '/<?xml version="1.0".*?>/';
          if (!preg_match($expect, $line)) {
            throw new Exception(
              pht(
                "Expected '%s', got %s.",
                $expect,
                $line));
          }
          $mode = 'list';
          break;
        case 'list':
          $expect = '<lists>';
          if ($line !== $expect) {
            throw new Exception(
              pht(
                "Expected '%s', got %s.",
                $expect,
                $line));
          }
          $mode = 'list1';
          break;
        case 'list1':
          $expect = '<list';
          if ($line !== $expect) {
            throw new Exception(
              pht(
                "Expected '%s', got %s.",
                $expect,
                $line));
          }
          $mode = 'list2';
          break;
        case 'list2':
          if (!preg_match('/^\s+path="/', $line)) {
            throw new Exception(
              pht(
                "Expected '%s', got %s.",
                '   path=...',
                $line));
          }
          $mode = 'entry-or-end';
          break;
      }
    }
    if (!$done) {
      throw new Exception(pht('Unexpected end of file.'));
    }

    return $map;
  }

  // TODO: Replace with DiffusionPathIDQuery::getParentPath().
  private function getParentPath($path) {
    $path = rtrim($path, '/');
    $path = dirname($path);
    if (!$path) {
      $path = '/';
    }
    return $path;
  }

  // TODO: Replace with DiffusionPathIDQuery::expandPathToRoot().
  private function expandAllParentPaths($path, $include_self = false) {
    $parents = array();
    if ($include_self) {
      $parents[] = '/'.rtrim($path, '/');
    }
    $parts = explode('/', trim($path, '/'));
    while (count($parts) >= 1) {
      array_pop($parts);
      $parents[] = '/'.implode('/', $parts);
    }
    return $parents;
  }

  private function getSVNLogXMLObject(
    PhabricatorRepository $repository,
    $uri,
    $revision) {
    list($xml) = $repository->execxRemoteCommand(
      'log --xml --verbose --limit 1 %s@%d',
      $uri,
      $revision);

    // Subversion may send us back commit messages which won't parse because
    // they have non UTF-8 garbage in them. Slam them into valid UTF-8.
    $xml = phutil_utf8ize($xml);

    return new SimpleXMLElement($xml);
  }

}
