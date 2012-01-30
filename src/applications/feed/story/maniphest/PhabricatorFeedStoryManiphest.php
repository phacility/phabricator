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

class PhabricatorFeedStoryManiphest extends PhabricatorFeedStory {

  public function getRequiredHandlePHIDs() {
    $data = $this->getStoryData();
    return array_filter(
        array(
        $this->getStoryData()->getAuthorPHID(),
        $data->getValue('taskPHID'),
        $data->getValue('ownerPHID'),
      ));
  }

  public function getRequiredObjectPHIDs() {
    return array(
      $this->getStoryData()->getAuthorPHID(),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $author_phid = $data->getAuthorPHID();
    $owner_phid = $data->getValue('ownerPHID');
    $task_phid = $data->getValue('taskPHID');

    $objects = $this->getObjects();
    $action = $data->getValue('action');

    $view = new PhabricatorFeedStoryView();

    $verb = ManiphestAction::getActionPastTenseVerb($action);
    $extra = null;
    switch ($action) {
      case ManiphestAction::ACTION_ASSIGN:
        if ($owner_phid) {
          $extra =
            ' to '.
            $this->linkTo($owner_phid);
        } else {
          $verb = 'placed';
          $extra = ' up for grabs';
        }
        break;
    }

    $title =
      $this->linkTo($author_phid).
      " {$verb} task ".
      $this->linkTo($task_phid);
    $title .= $extra;
    $title .= '.';

    $view->setTitle($title);

    switch ($action) {
      case ManiphestAction::ACTION_CREATE:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    $view->setEpoch($data->getEpoch());

    if ($full_size) {
      $view->setImage($this->getHandle($author_phid)->getImageURI());
      $content = $this->renderSummary($data->getValue('description'));
      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
