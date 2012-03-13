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

/**
 * @group console
 */
final class DarkConsoleEventPlugin extends DarkConsolePlugin {

  public function getName() {
    return 'Events';
  }

  public function getDescription() {
    return 'Information about Phabricator events and event listeners.';
  }

  public function generateData() {

    $listeners = PhutilEventEngine::getInstance()->getAllListeners();
    foreach ($listeners as $key => $listener) {
      $listeners[$key] = array(
        'id'      => $listener->getListenerID(),
        'class'   => get_class($listener),
      );
    }

    $events = DarkConsoleEventPluginAPI::getEvents();
    foreach ($events as $key => $event) {
      $events[$key] = array(
        'type'    => $event->getType(),
        'stopped' => $event->isStopped(),
      );
    }

    return array(
      'listeners' => $listeners,
      'events'    => $events,
    );
  }

  public function render() {
    $data = $this->getData();

    $out = array();

    $out[] =
      '<div class="dark-console-panel-header">'.
        '<h1>Registered Event Listeners</h1>'.
      '</div>';

    $rows = array();
    foreach ($data['listeners'] as $listener) {
      $rows[] = array(
        phutil_escape_html($listener['id']),
        phutil_escape_html($listener['class']),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Internal ID',
        'Listener Class',
      ));
    $table->setColumnClasses(
      array(
        '',
        'wide',
      ));

    $out[] = $table->render();

    $out[] =
      '<div class="dark-console-panel-header">'.
        '<h1>Event Log</h1>'.
      '</div>';

    $rows = array();
    foreach ($data['events'] as $event) {
      $rows[] = array(
        phutil_escape_html($event['type']),
        $event['stopped'] ? 'STOPPED' : null,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'wide',
      ));
    $table->setHeaders(
      array(
        'Event Type',
        'Stopped',
      ));

    $out[] = $table->render();


    return implode("\n", $out);
  }
}
