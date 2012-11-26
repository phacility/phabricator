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

final class PhabricatorTimelineEventView extends AphrontView {

  private $userHandle;
  private $title;
  private $classes = array();
  private $disableStandardTitleStyle;
  private $disableStandardContentStyle;

  public function setUserHandle(PhabricatorObjectHandle $handle) {
    $this->userHandle = $handle;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setDisableStandardTitleStyle($disable) {
    $this->disableStandardTitleStyle = $disable;
    return $this;
  }

  public function setDisableStandardContentStyle($disable) {
    $this->disableStandardContentStyle = $disable;
    return $this;
  }

  public function render() {
    $content = $this->renderChildren();

    $title = $this->title;
    if (($title === null) && !strlen($content)) {
      $title = '';
    }

    if ($title !== null) {
      $title_classes = array();
      $title_classes[] = 'phabricator-timeline-title';
      if (!$this->disableStandardTitleStyle) {
        $title_classes[] = 'phabricator-timeline-standard-title';
      }

      $title = phutil_render_tag(
        'div',
        array(
          'class' => implode(' ', $title_classes),
        ),
        $title);
    }

    $wedge = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-timeline-wedge phabricator-timeline-border',
      ),
      '');

    $image_uri = $this->userHandle->getImageURI();
    $image = phutil_render_tag(
      'div',
      array(
        'style' => 'background-image: url('.$image_uri.')',
        'class' => 'phabricator-timeline-image',
      ),
      '');

    $content_classes = array();
    $content_classes[] = 'phabricator-timeline-content';
    if (!$this->disableStandardContentStyle) {
      $content_classes[] = 'phabricator-timeline-standard-content';
    }

    $classes = array();
    $classes[] = 'phabricator-timeline-event-view';
    $classes[] = 'phabricator-timeline-border';
    if ($content) {
      $classes[] = 'phabricator-timeline-major-event';
      $content = phutil_render_tag(
        'div',
        array(
          'class' => implode(' ', $content_classes),
        ),
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-timeline-inner-content',
          ),
          $title.
          phutil_render_tag(
            'div',
            array(
              'class' => 'phabricator-timeline-core-content',
            ),
            $content)));
      $content = $image.$wedge.$content;
    } else {
      $classes[] = 'phabricator-timeline-minor-event';
      $content = phutil_render_tag(
        'div',
        array(
          'class' => implode(' ', $content_classes),
        ),
        $image.$wedge.$title);
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => implode(' ', $this->classes),
      ),
      phutil_render_tag(
        'div',
        array(
          'class' => implode(' ', $classes),
        ),
        $content));
  }

}
