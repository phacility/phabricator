<?php

final class DarkConsoleEventPlugin extends DarkConsolePlugin {

  public function getName() {
    return pht('Events');
  }

  public function getDescription() {
    return pht('Information about Phabricator events and event listeners.');
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

  public function renderPanel() {
    $data = $this->getData();

    $out = array();

    $out[] = phutil_tag(
      'div',
      array('class' => 'dark-console-panel-header'),
      phutil_tag('h1', array(), pht('Registered Event Listeners')));

    $rows = array();
    foreach ($data['listeners'] as $listener) {
      $rows[] = array($listener['id'], $listener['class']);
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Internal ID'),
        pht('Listener Class'),
      ));
    $table->setColumnClasses(
      array(
        '',
        'wide',
      ));

    $out[] = $table->render();

    $out[] = phutil_tag(
      'div',
      array('class' => 'dark-console-panel-header'),
      phutil_tag('h1', array(), pht('Event Log')));

    $rows = array();
    foreach ($data['events'] as $event) {
      $rows[] = array(
        $event['type'],
        $event['stopped'] ? pht('STOPPED') : null,
      );
    }

    $table = new AphrontTableView($rows);
    $table->setColumnClasses(
      array(
        'wide',
      ));
    $table->setHeaders(
      array(
        pht('Event Type'),
        pht('Stopped'),
      ));

    $out[] = $table->render();


    return phutil_implode_html("\n", $out);
  }

}
