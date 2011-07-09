<?php

/*
 * Copyright 2011 Facebook, Inc.
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

final class PhabricatorFeedStoryPublisher {

  private $relatedPHIDs;
  private $storyType;
  private $storyData;
  private $storyTime;
  private $storyAuthorPHID;

  public function setRelatedPHIDs(array $phids) {
    $this->relatedPHIDs = $phids;
    return $this;
  }

  public function setStoryType($story_type) {
    $this->storyType = $story_type;
    return $this;
  }

  public function setStoryData(array $data) {
    $this->storyData = $data;
    return $this;
  }

  public function setStoryTime($time) {
    $this->storyTime = $time;
    return $this;
  }

  public function setStoryAuthorPHID($phid) {
    $this->storyAuthorPHID = $phid;
    return $this;
  }

  public function publish() {
    if (!$this->relatedPHIDs) {
      throw new Exception("There are no PHIDs related to this story!");
    }

    if (!$this->storyType) {
      throw new Exception("Call setStoryType() before publishing!");
    }

    $chrono_key = $this->generateChronologicalKey();

    $story = new PhabricatorFeedStoryData();
    $story->setStoryType($this->storyType);
    $story->setStoryData($this->storyData);
    $story->setAuthorPHID($this->storyAuthorPHID);
    $story->setChronologicalKey($chrono_key);
    $story->save();

    $ref = new PhabricatorFeedStoryReference();

    $sql = array();
    $conn = $ref->establishConnection('w');
    foreach (array_unique($this->relatedPHIDs) as $phid) {
      $sql[] = qsprintf(
        $conn,
        '(%s, %s)',
        $phid,
        $chrono_key);
    }

    queryfx(
      $conn,
      'INSERT INTO %T (objectPHID, chronologicalKey) VALUES %Q',
      $ref->getTableName(),
      implode(', ', $sql));

    return $story;
  }

  /**
   * We generate a unique chronological key for each story type because we want
   * to be able to page through the stream with a cursor (i.e., select stories
   * after ID = X) so we can efficiently perform filtering after selecting data,
   * and multiple stories with the same ID make this cumbersome without putting
   * a bunch of logic in the client. We could use the primary key, but that
   * would prevent publishing stories which happened in the past. Since it's
   * potentially useful to do that (e.g., if you're importing another data
   * source) build a unique key for each story which has chronological ordering.
   *
   * @return string A unique, time-ordered key which identifies the story.
   */
  private function generateChronologicalKey() {
    // Use the epoch timestamp for the upper 32 bits of the key. Default to
    // the current time if the story doesn't have an explicit timestamp.
    $time = nonempty($this->storyTime, time());

    // Generate a random number for the lower 32 bits of the key.
    $rand = head(unpack('L', Filesystem::readRandomBytes(4)));

    return ($time << 32) + ($rand);
  }
}
