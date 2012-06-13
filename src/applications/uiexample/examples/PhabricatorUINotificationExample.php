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

final class PhabricatorUINotificationExample extends PhabricatorUIExample {

  public function getName() {
    return 'Notifications';
  }

  public function getDescription() {
    return 'Use <tt>JX.Notification</tt> to create notifications.';
  }

  public function renderExample() {

    require_celerity_resource('phabricator-notification-css');
    Javelin::initBehavior('phabricator-notification-example');

    $content = javelin_render_tag(
      'a',
      array(
        'sigil' => 'notification-example',
        'class' => 'button green',
      ),
      'Show Notification');

    $content = '<div style="padding: 1em 3em;">'.$content.'</content>';

    return $content;
  }
}
