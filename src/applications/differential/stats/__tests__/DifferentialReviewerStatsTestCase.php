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

final class DifferentialReviewerStatsTestCase extends PhabricatorTestCase {

  public function testReviewerStats() {
    $revision = new DifferentialRevision();
    $revision->setDateCreated(1);
    $revision->attachRelationships(array(
      $this->newReviewer('R1'),
      $this->newReviewer('R3'),
    ));

    $comments = array(
      $this->newComment(2, 'A', DifferentialAction::ACTION_COMMENT),
      $this->newComment(4, 'A', DifferentialAction::ACTION_ADDREVIEWERS,
        array(DifferentialComment::METADATA_ADDED_REVIEWERS => array('R3'))),
      $this->newComment(8, 'R1', DifferentialAction::ACTION_REJECT),
      $this->newComment(16, 'A', DifferentialAction::ACTION_COMMENT),
      $this->newComment(32, 'A', DifferentialAction::ACTION_UPDATE),
      $this->newComment(64, 'A', DifferentialAction::ACTION_UPDATE),
      $this->newComment(128, 'A', DifferentialAction::ACTION_COMMENT),
      $this->newComment(256, 'R2', DifferentialAction::ACTION_RESIGN,
        array(DifferentialComment::METADATA_REMOVED_REVIEWERS => array('R2'))),
      $this->newComment(512, 'R3', DifferentialAction::ACTION_ACCEPT),
      $this->newComment(1024, 'A', DifferentialAction::ACTION_UPDATE),
      // TODO: claim, abandon, reclaim
    );

    $stats = new DifferentialReviewerStats();
    list($reviewed, $not_reviewed) = $stats->computeTimes($revision, $comments);

    ksort($reviewed);
    $this->assertEqual(
      array(
        'R1' => array(8 - 1),
        'R2' => array(256 - 32),
        'R3' => array(512 - 32),
      ),
      $reviewed);

    ksort($not_reviewed);
    $this->assertEqual(
      array(
        'R1' => array(512 - 32),
        'R2' => array(8 - 1),
        'R3' => array(8 - 4),
      ),
      $not_reviewed);
  }

  public function testReviewerStatsSince() {
    $revision = new DifferentialRevision();
    $revision->setDateCreated(1);
    $revision->attachRelationships(array($this->newReviewer('R')));

    $comments = array(
      $this->newComment(2, 'R', DifferentialAction::ACTION_REJECT),
      $this->newComment(4, 'A', DifferentialAction::ACTION_REQUEST),
      $this->newComment(8, 'R', DifferentialAction::ACTION_ACCEPT),
    );

    $stats = new DifferentialReviewerStats();
    $stats->setSince(4);
    list($reviewed, $not_reviewed) = $stats->computeTimes($revision, $comments);

    $this->assertEqual(array('R' => array(8 - 4)), $reviewed);
    $this->assertEqual(array(), $not_reviewed);
  }

  public function testReviewerStatsRequiresReview() {
    $revision = new DifferentialRevision();
    $revision->setDateCreated(1);
    $revision->attachRelationships(array($this->newReviewer('R')));

    $comments = array();

    $stats = new DifferentialReviewerStats();
    $stats->setUntil(2);
    list($reviewed, $not_reviewed) = $stats->computeTimes($revision, $comments);

    $this->assertEqual(array(), $reviewed);
    $this->assertEqual(array('R' => array(2 - 1)), $not_reviewed);
  }

  private function newReviewer($phid) {
    return array(
      'relation' => DifferentialRevision::RELATION_REVIEWER,
      'objectPHID' => $phid,
    );
  }

  private function newComment($date, $author, $action, $metadata = array()) {
    return id(new DifferentialComment())
      ->setDateCreated($date)
      ->setAuthorPHID($author)
      ->setAction($action)
      ->setMetadata($metadata);
  }

}
