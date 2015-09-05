<?php

final class ReleephRequestTypeaheadController
  extends PhabricatorTypeaheadDatasourceController {

  public function handleRequest(AphrontRequest $request) {
    $query    = $request->getStr('q');
    $repo_id  = $request->getInt('repo');
    $since    = $request->getInt('since');
    $limit    = $request->getInt('limit');

    $now = time();
    $data = array();

    // Dummy instances used for getting connections, table names, etc.
    $pr_commit = new PhabricatorRepositoryCommit();
    $pr_commit_data = new PhabricatorRepositoryCommitData();

    $conn = $pr_commit->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT
        rc.phid as commitPHID,
        rc.authorPHID,
        rcd.authorName,
        SUBSTRING(rcd.commitMessage, 1, 100) AS shortMessage,
        rc.commitIdentifier,
        rc.epoch
        FROM %T rc
        INNER JOIN %T rcd ON rcd.commitID = rc.id
        WHERE repositoryID = %d
        AND rc.epoch >= %d
        AND (
          rcd.commitMessage LIKE %~
          OR
          rc.commitIdentifier LIKE %~
        )
        ORDER BY rc.epoch DESC
        LIMIT %d',
      $pr_commit->getTableName(),
      $pr_commit_data->getTableName(),
      $repo_id,
      $since,
      $query,
      $query,
      $limit);

    foreach ($rows as $row) {
      $full_commit_id = $row['commitIdentifier'];
      $short_commit_id = substr($full_commit_id, 0, 12);
      $first_line = $this->getFirstLine($row['shortMessage']);
      $data[] = array(
        $full_commit_id,
        $short_commit_id,
        $row['authorName'],
        phutil_format_relative_time($now - $row['epoch']),
        $first_line,
      );
    }

    return id(new AphrontAjaxResponse())
      ->setContent($data);
  }

  /**
   * Split either at the first new line, or a bunch of dashes.
   *
   * Really just a legacy from old Releeph Daemon commit messages where I used
   * to say:
   *
   *   Commit of FOO for BAR
   *   ------------
   *   This does X Y Z
   *
   */
  private function getFirstLine($commit_message_fragment) {
    static $separators = array('-------', "\n");
    $string = ltrim($commit_message_fragment);
    $first_line = $string;
    foreach ($separators as $separator) {
      if ($pos = strpos($string, $separator)) {
        $first_line = substr($string, 0, $pos);
        break;
      }
    }
    return $first_line;
  }

}
