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

final class FeedPublisherWorker extends PhabricatorWorker {

  protected function doWork() {
    $task_data  = $this->getTaskData();
    $chrono_key = $task_data['chrono_key'];
    $uri        = $task_data['uri'];

    $story = id(new PhabricatorFeedStoryData())
      ->loadOneWhere('chronologicalKey = %s', $chrono_key);

    if (!$story) {
      throw new PhabricatorWorkerPermanentFailureException(
        'Feed story was deleted.'
      );
    }

    $data = array(
      'storyID'         => $story->getID(),
      'storyType'       => $story->getStoryType(),
      'storyData'       => $story->getStoryData(),
      'storyAuthorPHID' => $story->getAuthorPHID(),
      'epoch'           => $story->getEpoch(),
    );

    id(new HTTPFuture($uri, $data))
      ->setMethod('POST')
      ->setTimeout(30)
      ->resolvex();

  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return max($task->getFailureCount(), 1) * 60;
  }

}
