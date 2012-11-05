<?php

final class DifferentialReviewerStats {
  private $since = 0;
  private $until;

  public function setSince($value) {
    $this->since = $value;
    return $this;
  }

  public function setUntil($value) {
    $this->until = $value;
    return $this;
  }

  /**
   * @return array($reviewed, $not_reviewed)
   */
  public function computeTimes(
    DifferentialRevision $revision,
    array $comments) {
    assert_instances_of($comments, 'DifferentialComment');

    $add_rev = DifferentialComment::METADATA_ADDED_REVIEWERS;
    $rem_rev = DifferentialComment::METADATA_REMOVED_REVIEWERS;

    $date = $revision->getDateCreated();

    // Find out original reviewers.
    $reviewers = array_fill_keys($revision->getReviewers(), $date);
    foreach (array_reverse($comments) as $comment) {
      $metadata = $comment->getMetadata();
      foreach (idx($metadata, $add_rev, array()) as $phid) {
        unset($reviewers[$phid]);
      }
      foreach (idx($metadata, $rem_rev, array()) as $phid) {
        $reviewers[$phid] = $date;
      }
    }

    $reviewed = array();
    $not_reviewed = array();
    $status = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;

    foreach ($comments as $comment) {
      $date = $comment->getDateCreated();
      $old_status = $status;

      switch ($comment->getAction()) {
        case DifferentialAction::ACTION_UPDATE:
          if ($status != ArcanistDifferentialRevisionStatus::CLOSED &&
              $status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
            $status = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
          }
          break;
        case DifferentialAction::ACTION_REQUEST:
        case DifferentialAction::ACTION_RECLAIM:
          $status = ArcanistDifferentialRevisionStatus::NEEDS_REVIEW;
          break;
        case DifferentialAction::ACTION_REJECT:
        case DifferentialAction::ACTION_RETHINK:
          $status = ArcanistDifferentialRevisionStatus::NEEDS_REVISION;
          break;
        case DifferentialAction::ACTION_ACCEPT:
          $status = ArcanistDifferentialRevisionStatus::ACCEPTED;
          break;
        case DifferentialAction::ACTION_CLOSE:
          $status = ArcanistDifferentialRevisionStatus::CLOSED;
          break;
        case DifferentialAction::ACTION_ABANDON:
          $status = ArcanistDifferentialRevisionStatus::ABANDONED;
          break;
      }

      // Update current reviewers.
      $metadata = $comment->getMetadata();
      foreach (idx($metadata, $add_rev, array()) as $phid) {
        // If someone reviewed a revision without being its reviewer then give
        // him zero response time.
        $reviewers[$phid] = $date;
      }
      foreach (idx($metadata, $rem_rev, array()) as $phid) {
        $start = idx($reviewers, $phid);
        if ($start !== null) {
          if ($date >= $this->since) {
            $reviewed[$phid][] = $date - $start;
          }
          unset($reviewers[$phid]);
        }
      }

      // TODO: Respect workdays and status away.

      if ($old_status != $status) {
        if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
          $reviewers = array_fill_keys(array_keys($reviewers), $date);
        } else if ($date >= $this->since) {
          if ($old_status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
            foreach ($reviewers as $phid => $start) {
              if ($phid == $comment->getAuthorPHID()) {
                $reviewed[$phid][] = $date - $start;
              } else {
                $not_reviewed[$phid][] = $date - $start;
              }
            }
          }
        }
      }
    }

    if ($status == ArcanistDifferentialRevisionStatus::NEEDS_REVIEW) {
      $date = ($this->until !== null ? $this->until : time());
      if ($date >= $this->since) {
        foreach ($reviewers as $phid => $start) {
          $not_reviewed[$phid][] = $date - $start;
        }
      }
    }

    return array($reviewed, $not_reviewed);
  }

  public function loadAvgs() {
    $limit = 1000;
    $conn_r = id(new DifferentialRevision())->establishConnection('r');

    $sums = array();
    $counts = array();
    $all_not_reviewed = array();

    $last_id = 0;
    do {
      $where = '';
      if ($this->until !== null) {
        $where .= qsprintf(
          $conn_r,
          ' AND dateCreated < %d',
          $this->until);
      }
      if ($this->since) {
        $where .= qsprintf(
          $conn_r,
          ' AND (dateModified > %d OR status = %s)',
          $this->since,
          ArcanistDifferentialRevisionStatus::NEEDS_REVIEW);
      }
      $revisions = id(new DifferentialRevision())->loadAllWhere(
        'id > %d%Q ORDER BY id LIMIT %d',
        $last_id,
        $where,
        $limit);

      if (!$revisions) {
        break;
      }
      $last_id = last_key($revisions);

      $relations = queryfx_all(
        $conn_r,
        'SELECT * FROM %T WHERE revisionID IN (%Ld) AND relation = %s',
        DifferentialRevision::RELATIONSHIP_TABLE,
        array_keys($revisions),
        DifferentialRevision::RELATION_REVIEWER);
      $relations = igroup($relations, 'revisionID');

      $where = '';
      if ($this->until !== null) {
        $where = qsprintf(
          $conn_r,
          ' AND dateCreated < %d',
          $this->until);
      }
      $all_comments = id(new DifferentialComment())->loadAllWhere(
        'revisionID IN (%Ld)%Q ORDER BY revisionID, id',
        array_keys($revisions),
        $where);
      $all_comments = mgroup($all_comments, 'getRevisionID');

      foreach ($revisions as $id => $revision) {
        $revision->attachRelationships(idx($relations, $id, array()));
        $comments = idx($all_comments, $id, array());

        list($reviewed, $not_reviewed) =
          $this->computeTimes($revision, $comments);

        foreach ($reviewed as $phid => $times) {
          $sums[$phid] = idx($sums, $phid, 0) + array_sum($times);
          $counts[$phid] = idx($counts, $phid, 0) + count($times);
        }

        foreach ($not_reviewed as $phid => $times) {
          $all_not_reviewed[$phid][] = $times;
        }
      }
    } while (count($revisions) >= $limit);

    foreach ($all_not_reviewed as $phid => $not_reviewed) {
      if (!array_key_exists($phid, $counts)) {
        // If the person didn't make any reviews than take maximum time because
        // he is at least that slow.
        $sums[$phid] = max(array_map('max', $not_reviewed));
        $counts[$phid] = 1;
        continue;
      }
      $avg = $sums[$phid] / $counts[$phid];
      foreach ($not_reviewed as $times) {
        foreach ($times as $time) {
          // Don't shorten the average time just because the reviewer was lucky
          // to be in a group with someone faster.
          if ($time > $avg) {
            $sums[$phid] += $time;
            $counts[$phid]++;
          }
        }
      }
    }

    $avgs = array();
    foreach ($sums as $phid => $sum) {
      $avgs[$phid] = $sum / $counts[$phid];
    }
    return $avgs;
  }

}
