<?php

final class PhabricatorMailManagementListInboundWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-inbound')
      ->setSynopsis('List inbound messages received by Phabricator.')
      ->setExamples(
        "**list-inbound**")
      ->setArguments(
        array(
          array(
            'name'    => 'limit',
            'param'   => 'N',
            'default' => 100,
            'help'    => 'Show a specific number of messages (default 100).',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = PhabricatorUser::getOmnipotentUser();

    $mails = id(new PhabricatorMetaMTAReceivedMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d',
      $args->getArg('limit'));

    if (!$mails) {
      $console->writeErr("%s\n", pht("No received mail."));
      return 0;
    }

    $phids = array_merge(
      mpull($mails, 'getRelatedPHID'),
      mpull($mails, 'getAuthorPHID'));
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    foreach (array_reverse($mails) as $mail) {
      $console->writeOut(
        "%s\n",
        sprintf(
          "% 8d  %-16s  %-20s  %s",
          $mail->getID(),
          $mail->getAuthorPHID()
            ? $handles[$mail->getAuthorPHID()]->getName()
            : '-',
          $mail->getRelatedPHID()
            ? $handles[$mail->getRelatedPHID()]->getName()
            : '-',
          $mail->getSubject()
            ? $mail->getSubject()
            : pht('(No subject.)')));
    }

    return 0;
  }

}
