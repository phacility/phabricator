<?php

final class PhabricatorMailManagementListOutboundWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-outbound')
      ->setSynopsis(pht('List outbound messages.'))
      ->setExamples('**list-outbound**')
      ->setArguments(
        array(
          array(
            'name'    => 'limit',
            'param'   => 'N',
            'default' => 100,
            'help'    => pht(
              'Show a specific number of messages (default 100).'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $mails = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d',
      $args->getArg('limit'));

    if (!$mails) {
      $console->writeErr("%s\n", pht('No sent mail.'));
      return 0;
    }

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',      array('title' => pht('ID')))
      ->addColumn('encrypt', array('title' => pht('#')))
      ->addColumn('status',  array('title' => pht('Status')))
      ->addColumn('type', array('title' => pht('Type')))
      ->addColumn('subject', array('title' => pht('Subject')));

    foreach (array_reverse($mails) as $mail) {
      $status = $mail->getStatus();

      $table->addRow(array(
        'id'      => $mail->getID(),
        'encrypt' => ($mail->getMustEncrypt() ? '#' : ' '),
        'status'  => PhabricatorMailOutboundStatus::getStatusName($status),
        'type' => $mail->getMessageType(),
        'subject' => $mail->getSubject(),
      ));
    }

    $table->draw();
    return 0;
  }

}
