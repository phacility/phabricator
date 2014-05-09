<?php

final class PhabricatorSMSManagementListOutboundWorkflow
  extends PhabricatorSMSManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-outbound')
      ->setSynopsis('List outbound sms messages sent by Phabricator.')
      ->setExamples(
        "**list-outbound**")
      ->setArguments(
        array(
          array(
            'name'    => 'limit',
            'param'   => 'N',
            'default' => 100,
            'help'    =>
              'Show a specific number of sms messages (default 100).',
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
      $console->writeErr("%s\n", pht("No sent sms."));
      return 0;
    }

    foreach (array_reverse($sms_messages) as $sms) {
      $console->writeOut(
        "%s\n",
        sprintf(
          "% 8d  %-8s  To: %s",
          $sms->getID(),
          $sms->getSendStatus(),
          $sms->getToNumber()));
    }

    return 0;
  }

}
