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

final class PhabricatorFeedPublicStreamController
  extends PhabricatorFeedController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {

    if (!PhabricatorEnv::getEnvConfig('feed.public')) {
      return new Aphront404Response();
    }

    // TODO: Profile images won't render correctly for logged-out users.

    $request = $this->getRequest();

    $query = new PhabricatorFeedQuery();
    $stories = $query->execute();

    $handles = array();
    $objects = array();
    if ($stories) {
      $handle_phids = array_mergev(mpull($stories, 'getRequiredHandlePHIDs'));
      $object_phids = array_mergev(mpull($stories, 'getRequiredObjectPHIDs'));
      $handles = id(new PhabricatorObjectHandleData($handle_phids))
        ->loadHandles();
      $objects = id(new PhabricatorObjectHandleData($object_phids))
        ->loadObjects();
    }

    // TODO: We need this for timezones but should develop some more general
    // solution for logged-out pages.
    $dummy_user = new PhabricatorUser();

    $views = array();
    foreach ($stories as $story) {
      $story->setHandles($handles);
      $story->setObjects($objects);

      $view = $story->renderView();
      $view->setViewer($dummy_user);

      $views[] = $view->render();
    }

    return $this->buildStandardPageResponse(
      $views,
      array(
        'title'   => 'Public Feed',
        'public'  => true,
      ));
  }
}
