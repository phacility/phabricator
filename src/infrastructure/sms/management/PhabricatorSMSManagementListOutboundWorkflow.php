<?php

final class PhabricatorSMSManagementListOutboundWorkflow
  extends PhabricatorSMSManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-outbound')
      ->setSynopsis(pht('List outbound SMS messages sent by Phabricator.'))
      ->setExamples('**list-outbound**')
      ->setArguments(
        array(
          array(
            'name'    => 'limit',
            'param'   => 'N',
            'default' => 100,
            'help'    => pht(
              'Show a specific number of SMS messages (default 100).'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $sms_messages = id(new PhabricatorSMS())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d',
      $args->getArg('limit'));

    if (!$sms_messages) {
      $console->writeErr("%s\n", pht('No sent SMS.'));
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',     array('title' => pht('ID')))
      ->addColumn('status', array('title' => pht('Status')))
      ->addColumn('recv',   array('title' => pht('Recipient')));

    foreach (array_reverse($sms_messages) as $sms) {
      $table->addRow(array(
        'id'     => $sms->getID(),
        'status' => $sms->getSendStatus(),
        'recv'   => $sms->getToNumber(),
      ));
    }

    $table->draw();
    return 0;
  }

}
