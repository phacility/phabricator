<?php

final class PhabricatorNotificationStatusView extends AphrontTagView {

  protected function getTagAttributes() {
    if (!$this->getID()) {
      $this->setID(celerity_generate_unique_node_id());
    }

    Javelin::initBehavior(
      'aphlict-status',
      array(
        'nodeID' => $this->getID(),
        'pht' => array(
          'setup' => pht('Setting Up Client'),
          'open' => pht('Connected'),
          'closed' => pht('Disconnected'),
        ),
      ));

    return array(
      'class' => 'aphlict-connection-status',
    );
  }

  protected function getTagContent() {
    $have = PhabricatorEnv::getEnvConfig('notification.servers');
    if ($have) {
      return pht('Connecting...');
    } else {
      return pht('Notification server not enabled');
    }
  }

}
