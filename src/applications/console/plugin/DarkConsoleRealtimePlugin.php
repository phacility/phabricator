<?php

final class DarkConsoleRealtimePlugin extends DarkConsolePlugin {

  public function getName() {
    return pht('Realtime');
  }

  public function getColor() {
    return null;
  }

  public function getDescription() {
    return pht('Debugging console for real-time notifications.');
  }

  public function renderPanel() {
    return phutil_tag(
      'div',
      array(
        'id' => 'dark-console-realtime-log',
        'class' => 'dark-console-log-frame',
      ));
  }

}
