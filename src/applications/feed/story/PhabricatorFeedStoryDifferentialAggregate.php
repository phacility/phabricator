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

final class PhabricatorFeedStoryDifferentialAggregate
  extends PhabricatorFeedStoryAggregate {

  public function renderView() {
    return null;
  }


  public function renderNotificationView() {
    $data = $this->getStoryData();

    $task_link = $this->linkTo($data->getValue('revision_phid'));

    $authors = $this->getAuthorPHIDs();

    // TODO: These aren't really translatable because linkTo() returns a
    // string, not an object with a gender.

    switch (count($authors)) {
      case 1:
        $author = $this->linkTo(array_shift($authors));
        $title = pht(
          '%s made multiple updates to %s',
          $author,
          $task_link);
        break;
      case 2:
        $author1 = $this->linkTo(array_shift($authors));
        $author2 = $this->linkTo(array_shift($authors));
        $title = pht(
          '%s and %s made multiple updates to %s',
          $author1,
          $author2,
          $task_link);
        break;
      case 3:
        $author1 = $this->linkTo(array_shift($authors));
        $author2 = $this->linkTo(array_shift($authors));
        $author3 = $this->linkTo(array_shift($authors));
        $title = pht(
          '%s, %s, and %s made multiple updates to %s',
          $author1,
          $author2,
          $author3,
          $task_link);
        break;
      default:
        $author1 = $this->linkTo(array_shift($authors));
        $author2 = $this->linkTo(array_shift($authors));
        $others  = count($authors);
        $title = pht(
          '%s, %s, and %d others made multiple updates to %s',
          $author1,
          $author2,
          $others,
          $task_link);
        break;
    }

    $view = new PhabricatorNotificationStoryView();
    $view->setEpoch($this->getEpoch());
    $view->setViewed($this->getHasViewed());
    $view->setTitle($title);

    return $view;
  }

}
