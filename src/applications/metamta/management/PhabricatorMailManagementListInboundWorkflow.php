<?php

final class PhabricatorMailManagementListInboundWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-inbound')
      ->setSynopsis(pht('List inbound messages.'))
      ->setExamples(
        '**list-inbound**')
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

    $mails = id(new PhabricatorMetaMTAReceivedMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d',
      $args->getArg('limit'));

    if (!$mails) {
      $console->writeErr("%s\n", pht('No received mail.'));
      return 0;
    }

    $phids = array_merge(
      mpull($mails, 'getRelatedPHID'),
      mpull($mails, 'getAuthorPHID'));
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    $table = id(new PhutilConsoleTable())
      ->setShowHeader(false)
      ->addColumn('id',      array('title' => pht('ID')))
      ->addColumn('author',  array('title' => pht('Author')))
      ->addColumn('phid',    array('title' => pht('Related PHID')))
      ->addColumn('subject', array('title' => pht('Subject')));

    foreach (array_reverse($mails) as $mail) {
      $table->addRow(array(
        'id'      => $mail->getID(),
        'author'  => $mail->getAuthorPHID()
                       ? $handles[$mail->getAuthorPHID()]->getName()
                       : '-',
        'phid'    => $mail->getRelatedPHID()
                       ? $handles[$mail->getRelatedPHID()]->getName()
                       : '-',
        'subject' => $mail->getSubject()
                       ? $mail->getSubject()
                       : pht('(No subject.)'),
      ));
    }

    $table->draw();
    return 0;
  }

}
