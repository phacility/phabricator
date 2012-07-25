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

final class DifferentialReviewerStats {
  private $since = 0;
  private $now;

  public function setSince($value) {
    $this->since = $value;
    return $this;
  }

  public function setNow($value) {
    $this->now = $value;
    return $this;
  }

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

      // TODO: Respect workdays.

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
      $now = ($this->now !== null ? $this->now : time());
      if ($now >= $this->since) {
        foreach ($reviewers as $phid => $start) {
          $not_reviewed[$phid][] = $now - $start;
        }
      }
    }

    return array($reviewed, $not_reviewed);
  }

}
