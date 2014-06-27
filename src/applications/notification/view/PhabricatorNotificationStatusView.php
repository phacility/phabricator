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
          'start' => pht('Starting Client'),
          'ready' => pht('Ready to Connect'),
          'connecting' => pht('Connecting...'),
          'connected' => pht('Connected'),
          'error' => pht('Connection Error'),
          'client' => pht('Connected Locally'),

          'error.flash.xdomain' => pht(
            'Unable to connect to Flash Policy Server. Check that the '.
            'notification server is running and port 843 is not firewalled.'),
          'error.flash.disconnected' => pht(
            'Disconnected from notification server.'),
        ),
      ));

    return array(
      'class' => 'aphlict-connection-status',
    );
  }

}
