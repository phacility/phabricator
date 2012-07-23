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

final class PhabricatorFeedPublicStreamController
  extends PhabricatorFeedController {

  public function shouldRequireLogin() {
    return false;
  }

  public function processRequest() {
    if (!PhabricatorEnv::getEnvConfig('feed.public')) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $query = new PhabricatorFeedQuery();
    $query->setViewer($viewer);
    $query->setLimit(100);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder
      ->setFramed(true)
      ->setUser($viewer);

    $view = $builder->buildView();

    return $this->buildStandardPageResponse(
      $view,
      array(
        'title'   => 'Public Feed',
        'public'  => true,
      ));
  }
}
