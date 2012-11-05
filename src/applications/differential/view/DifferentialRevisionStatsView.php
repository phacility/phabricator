<?php

/**
 * Render some distracting statistics on revisions
 */
final class DifferentialRevisionStatsView extends AphrontView {
  private $comments;
  private $revisions;
  private $diffs;
  private $user;
  private $filter;

  public function setRevisions(array $revisions) {
    assert_instances_of($revisions, 'DifferentialRevision');
    $this->revisions = $revisions;
    return $this;
  }

  public function setComments(array $comments) {
    assert_instances_of($comments, 'DifferentialComment');
    $this->comments = $comments;
    return $this;
  }

  public function setDiffs(array $diffs) {
    assert_instances_of($diffs, 'DifferentialDiff');
    $this->diffs = $diffs;
    return $this;
  }

  public function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $user = $this->user;
    if (!$user) {
      throw new Exception("Call setUser() before render()!");
    }

    $id_to_revision_map = array();
    foreach ($this->revisions as $rev) {
      $id_to_revision_map[$rev->getID()] = $rev;
    }
    $revisions_seen = array();

    $dates = array();
    $counts = array();
    $lines = array();
    $days_with_diffs = array();
    $count_active = array();
    $response_time = array();
    $response_count = array();
    $now = time();
    $row_array = array();

    foreach (array(
               '1 week', '2 weeks', '3 weeks',
               '1 month', '2 months', '3 months', '6 months', '9 months',
               '1 year', '18 months',
               '2 years', '3 years', '4 years', '5 years',
             ) as $age) {
      $dates[$age] = strtotime($age . ' ago 23:59:59');
      $counts[$age] = 0;
      $lines[$age] = 0;
      $count_active[$age] = 0;
      $response_time[$age] = array();
    }

    $revision_diffs_map = mgroup($this->diffs, 'getRevisionID');
    foreach ($revision_diffs_map as $revision_id => $diffs) {
      $revision_diffs_map[$revision_id] = msort($diffs, 'getID');
    }

    foreach ($this->comments as $comment) {
      $comment_date = $comment->getDateCreated();

      $day = phabricator_date($comment_date, $user);
      $old_daycount = idx($days_with_diffs, $day, 0);
      $days_with_diffs[$day] = $old_daycount + 1;

      $rev_id = $comment->getRevisionID();

      if (idx($revisions_seen, $rev_id)) {
        $revision_seen = true;
        $rev = null;
      } else {
        $revision_seen = false;
        $rev = $id_to_revision_map[$rev_id];
        $revisions_seen[$rev_id] = true;
      }

      foreach ($dates as $age => $cutoff) {
        if ($cutoff >= $comment_date) {
          continue;
        }

        if (!$revision_seen) {
          if ($rev) {
            $lines[$age] += $rev->getLineCount();
          }
          $counts[$age]++;
          if (!$old_daycount) {
            $count_active[$age]++;
          }
        }

        $diffs = $revision_diffs_map[$rev_id];
        $target_diff = $this->findTargetDiff($diffs, $comment);
        if ($target_diff) {
          $response_time[$age][] =
            $comment_date - $target_diff->getDateCreated();
        }
      }
    }

    $old_count = 0;
    foreach (array_reverse($dates) as $age => $cutoff) {
      $weeks = ceil(($now - $cutoff) / (60 * 60 * 24)) / 7;
      if ($old_count == $counts[$age] && count($row_array) == 1) {
        unset($dates[last_key($row_array)]);
        $row_array = array();
      }
      $old_count = $counts[$age];

      $row_array[$age] = array(
        'Revisions per week' => number_format($counts[$age] / $weeks, 2),
        'Lines per week' => number_format($lines[$age] / $weeks, 1),
        'Active days per week' =>
          number_format($count_active[$age] / $weeks, 1),
        'Revisions' => number_format($counts[$age]),
        'Lines' => number_format($lines[$age]),
        'Lines per diff' => number_format($lines[$age] /
                                          ($counts[$age] + 0.0001)),
        'Active days' => number_format($count_active[$age]),
      );

      switch ($this->filter) {
        case DifferentialAction::ACTION_CLOSE:
        case DifferentialAction::ACTION_UPDATE:
        case DifferentialAction::ACTION_COMMENT:
          break;
        case DifferentialAction::ACTION_ACCEPT:
        case DifferentialAction::ACTION_REJECT:
          $count = count($response_time[$age]);
          if ($count) {
            rsort($response_time[$age]);
            $median = $response_time[$age][round($count / 2) - 1];
            $average = array_sum($response_time[$age]) / $count;
          } else {
            $median = 0;
            $average = 0;
          }

          $row_array[$age]['Response hours (median|average)'] =
            number_format($median / 3600, 1).
            ' | '.
            number_format($average / 3600, 1);
          break;
      }
    }

    $rows = array();
    $row_names = array_keys(head($row_array));
    foreach ($row_names as $row_name) {
      $rows[] = array($row_name);
    }
    foreach (array_keys($dates) as $age) {
      $i = 0;
      foreach ($row_names as $row_name) {
        $rows[$i][] = idx(idx($row_array, $age), $row_name, '-');
        ++$i;
      }
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'wide pri',
      ));

    $table->setHeaders(
      array_merge(
        array(
          'Metric',
        ),
        array_keys($dates)));

    return $table->render();
  }

  private function findTargetDiff(array $diffs,
                                         DifferentialComment $comment) {
    switch ($this->filter) {
      case DifferentialAction::ACTION_CLOSE:
      case DifferentialAction::ACTION_UPDATE:
      case DifferentialAction::ACTION_COMMENT:
        return null;
      case DifferentialAction::ACTION_ACCEPT:
      case DifferentialAction::ACTION_REJECT:
        $result = head($diffs);
        foreach ($diffs as $diff) {
          if ($diff->getDateCreated() >= $comment->getDateCreated()) {
            break;
          }
          $result = $diff;
        }

        return $result;
    }
  }
}
