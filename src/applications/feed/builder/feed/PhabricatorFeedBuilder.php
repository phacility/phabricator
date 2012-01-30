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

final class PhabricatorFeedBuilder {

  private $stories;
  private $framed;

  public function __construct(array $stories) {
    $this->stories = $stories;
  }

  public function setFramed($framed) {
    $this->framed = $framed;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function buildView() {
    if (!$this->user) {
      throw new Exception('Call setUser() before buildView()!');
    }

    $user = $this->user;
    $stories = $this->stories;

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

    $null_view = new AphrontNullView();

    require_celerity_resource('phabricator-feed-css');

    $last_date = null;
    $today = phabricator_date(time(), $user);
    foreach ($stories as $story) {
      $story->setHandles($handles);
      $story->setObjects($objects);
      $story->setFramed($this->framed);

      $date = phabricator_date($story->getEpoch(), $user);
      if ($date == $today) {
        $date = 'Today';
      }

      if ($date !== $last_date) {
        if ($last_date !== null) {
          $null_view->appendChild(
            '<div class="phabricator-feed-story-date-separator"></div>');
        }
        $last_date = $date;
        $null_view->appendChild(
          phutil_render_tag(
            'div',
            array(
              'class' => 'phabricator-feed-story-date',
            ),
            phutil_escape_html($date)));
      }

      $view = $story->renderView();
      $view->setViewer($user);

      $null_view->appendChild($view);
    }

    return id(new AphrontNullView())->appendChild(
      '<div class="phabricator-feed-frame">'.
        $null_view->render().
      '</div>');
  }

}
