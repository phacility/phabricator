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

final class PhabricatorDaemonTimelineEventController
  extends PhabricatorDaemonController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $event = id(new PhabricatorTimelineEvent('NULL'))->load($this->id);
    if (!$event) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($event->getDataID()) {
      $data = id(new PhabricatorTimelineEventData())->load(
        $event->getDataID());
    }

    if ($data) {
      $data = json_encode($data->getEventData());
    } else {
      $data = 'null';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('ID')
          ->setValue($event->getID()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Type')
          ->setValue($event->getType()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setDisabled(true)
          ->setLabel('Data')
          ->setValue($data))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/daemon/timeline/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Event');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('timeline');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Timeline Event',
      ));
  }

}
