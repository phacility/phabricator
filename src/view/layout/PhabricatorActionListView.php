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

final class PhabricatorActionListView extends AphrontView {

  private $actions = array();
  private $object;
  private $user;

  public function setObject(PhabricatorLiskDAO $object) {
    $this->object = $object;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function addAction(PhabricatorActionView $view) {
    $this->actions[] = $view;
    return $this;
  }

  public function render() {
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    $event = new PhabricatorEvent(
      PhabricatorEventType::TYPE_UI_DIDRENDERACTIONS,
      array(
        'object'  => $this->object,
        'actions' => $this->actions,
      ));
    $event->setUser($this->user);
    PhutilEventEngine::dispatchEvent($event);

    $actions = $event->getValue('actions');

    if (!$actions) {
      return null;
    }

    require_celerity_resource('phabricator-action-list-view-css');
    return phutil_render_tag(
      'ul',
      array(
        'class' => 'phabricator-action-list-view',
      ),
      $this->renderSingleView($actions));
  }


}
