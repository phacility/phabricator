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
    $handles = $this->getHandles();
    $action = $data->getValue('action');

    $view = new PhabricatorFeedStoryView();

    $verb = ManiphestAction::getActionPastTenseVerb($action);
    $extra = null;
    switch ($action) {
      case ManiphestAction::ACTION_ASSIGN:
        if ($owner_phid) {
          $extra =
            ' to '.
            '<strong>'.$this->getHandle($owner_phid)->renderLink().'</strong>';
        } else {
          $verb = 'placed';
          $extra = ' up for grabs';
        }
        break;
    }

    $title =
      '<strong>'.$this->getHandle($author_phid)->renderLink().'</strong>'.
      " {$verb} task ".
      '<strong>'.$this->getHandle($task_phid)->renderLink().'</strong>';
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
      if (!empty($handles[$author_phid])) {
        $image_uri = $handles[$author_phid]->getImageURI();
        $view->setImage($image_uri);
      }

      $content = phutil_escape_html(
        phutil_utf8_shorten($data->getValue('description'), 128));
      $content = str_replace("\n", '<br />', $content);

      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
