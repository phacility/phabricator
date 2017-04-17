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
    $frame = phutil_tag(
      'div',
      array(
        'id' => 'dark-console-realtime-log',
        'class' => 'dark-console-log-frame',
      ));

    $reconnect_label = pht('Reconnect');

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'dark-console-realtime-actions',
      ),
      array(
        id(new PHUIButtonView())
          ->setIcon('fa-refresh')
          ->setColor(PHUIButtonView::GREY)
          ->setText($reconnect_label)
          ->addSigil('dark-console-realtime-action')
          ->setMetadata(
            array(
              'action' => 'reconnect',
              'label' => $reconnect_label,
            )),
      ));

    return phutil_tag(
      'div',
      array(
      ),
      array(
        $buttons,
        $frame,
      ));
  }

}
