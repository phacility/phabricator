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

final class PhabricatorFeedStoryView extends PhabricatorFeedView {

  private $title;
  private $image;
  private $phid;
  private $epoch;
  private $viewer;

  private $oneLine;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setImage($image) {
    $this->image = $image;
    return $this;
  }

  public function setOneLineStory($one_line) {
    $this->oneLine = $one_line;
    return $this;
  }

  public function render() {

    $head = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-feed-story-head',
      ),
      nonempty($this->title, 'Untitled Story'));

    $body = null;
    $foot = null;
    $image_style = null;

    if (!$this->oneLine) {
      $body = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-feed-story-body',
        ),
        $this->renderChildren());

      if ($this->epoch) {
        $foot = phabricator_datetime($this->epoch, $this->viewer);
      } else {
        $foot = '';
      }

      $foot = phutil_render_tag(
        'div',
        array(
          'class' => 'phabricator-feed-story-foot',
        ),
        $foot);

      if ($this->image) {
        $image_style = 'background-image: url('.$this->image.')';
      }
    }

    require_celerity_resource('phabricator-feed-css');

    return phutil_render_tag(
      'div',
      array(
        'class' => $this->oneLine
          ? 'phabricator-feed-story phabricator-feed-story-one-line'
          : 'phabricator-feed-story',
        'style' => $image_style,
      ),
      $head.$body.$foot);
  }

}
