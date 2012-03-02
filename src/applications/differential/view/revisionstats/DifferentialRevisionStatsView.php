<?php

/*
 * Copyright 2012 Facebook, Inc.
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

/**
 * Render some distracting statistics on revisions
 */
final class DifferentialRevisionStatsView extends AphrontView {
  private $comments;
  private $revisions;
  private $user;

  public function setRevisions(array $revisions) {
    $this->revisions = $revisions;
    return $this;
  }

  public function setComments(array $comments) {
    $this->comments = $comments;
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
    $boosts = array();
    $days_with_diffs = array();
    $count_active = array();
    $now = time();
    $row_array = array();

    foreach (array(
               '1 week', '2 weeks', '3 weeks',
               '1 month', '2 months', '3 months', '6 months', '9 months',
               '1 year', '18 months',
               '2 years', '3 years', '4 years', '5 years',
             ) as $age) {
      $dates[$age] = strtotime($age . ' ago');
      $counts[$age] = 0;
      $lines[$age] = 0;
      $count_active[$age] = 0;
    }

    foreach ($this->comments as $comment) {
      $rev_date = $comment->getDateCreated();

      $day = phabricator_date($rev_date, $user);
      $old_daycount = idx($days_with_diffs, $day, 0);
      $days_with_diffs[$day] = $old_daycount + 1;

      $rev_id = $comment->getRevisionID();

      if (idx($revisions_seen, $rev_id)) {
        continue;
      }
      $rev = $id_to_revision_map[$rev_id];
      $revisions_seen[$rev_id] = true;

      foreach ($dates as $age => $cutoff) {
        if ($cutoff > $rev_date) {
          continue;
        }
        if ($rev) {
          $lines[$age] += $rev->getLineCount();
        }
        $counts[$age]++;
        if (!$old_daycount) {
          $count_active[$age]++;
        }
      }
    }

    $old_count = 0;
    foreach (array_reverse($dates) as $age => $cutoff) {
      $weeks = ($now - $cutoff + 0.) / (7 * 60 * 60 * 24);
      if ($old_count == $counts[$age] && count($row_array) == 1) {
        end($row_array);
        unset($dates[key($row_array)]);
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
}
