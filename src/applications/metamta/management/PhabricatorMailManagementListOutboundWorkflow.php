<?php

final class PhabricatorMailManagementListOutboundWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-outbound')
      ->setSynopsis('List outbound messages sent by Phabricator.')
      ->setExamples(
        "**list-outbound**")
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

    $mails = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d',
      $args->getArg('limit'));

    if (!$mails) {
      $console->writeErr("%s\n", pht("No sent mail."));
      return 0;
    }

    foreach (array_reverse($mails) as $mail) {
      $console->writeOut(
        "%s\n",
        sprintf(
          "% 8d  %-8s  %s",
          $mail->getID(),
          PhabricatorMetaMTAMail::getReadableStatus($mail->getStatus()),
          $mail->getSubject()));
    }

    return 0;
  }

}
