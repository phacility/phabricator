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

class PhabricatorFeedStoryDifferential extends PhabricatorFeedStory {

  public function getRequiredHandlePHIDs() {
    $data = $this->getStoryData();
    return array(
      $this->getStoryData()->getAuthorPHID(),
      $data->getValue('revision_phid'),
      $data->getValue('revision_author_phid'),
    );
  }

  public function getRequiredObjectPHIDs() {
    return array(
      $this->getStoryData()->getAuthorPHID(),
    );
  }

  public function renderView() {
    $data = $this->getStoryData();

    $handles = $this->getHandles();
    $author_phid = $data->getAuthorPHID();

    $objects = $this->getObjects();

    $view = new PhabricatorFeedStoryView();

    $revision_phid = $data->getValue('revision_phid');

    $action = $data->getValue('action');
    $verb = DifferentialAction::getActionPastTenseVerb($action);

    $view->setTitle(
      '<strong>'.$handles[$author_phid]->renderLink().'</strong>'.
      ' '.$verb.' revision '.
      '<strong>'.$handles[$revision_phid]->renderLink().'</strong>.');
    $view->setEpoch($data->getEpoch());

    $action = $data->getValue('action');
    switch ($action) {
      case DifferentialAction::ACTION_CREATE:
      case DifferentialAction::ACTION_COMMIT:
        $full_size = true;
        break;
      default:
        $full_size = false;
        break;
    }

    if ($full_size) {
      if (!empty($handles[$author_phid])) {
        $image_uri = $handles[$author_phid]->getImageURI();
        $view->setImage($image_uri);
      }

      $content = phutil_escape_html($data->getValue('feedback_content'));
      $content = str_replace("\n", '<br />', $content);

      $view->appendChild($content);
    } else {
      $view->setOneLineStory(true);
    }

    return $view;
  }

}
