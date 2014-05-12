<?php

final class PhabricatorSMSManagementSendTestWorkflow
  extends PhabricatorSMSManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('send-test')
      ->setSynopsis(
        pht(
          'Simulate sending an sms. This may be useful to test your sms '.
          'configuration, or while developing new sms adapters.'))
      ->setExamples(
        "**send-test** --to 12345678 --body 'pizza time yet?'")
      ->setArguments(
        array(
          array(
            'name'    => 'to',
            'param'   => 'number',
            'help'    => 'Send sms "To:" the specified number.',
            'repeat'  => true,
          ),
          array(
            'name'    => 'body',
            'param'   => 'text',
            'help'    => 'Send sms with the specified body.',
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $tos = $args->getArg('to');
    $body = $args->getArg('body');

    PhabricatorWorker::setRunAllTasksInProcess(true);
    PhabricatorSMSImplementationAdapter::sendSMS($tos, $body);

    $console->writeErr(
      "%s\n\n    phabricator/ $ ./bin/sms list-outbound \n\n",
      pht(
        'Send completed! You can view the list of SMS messages sent by '.
        'running this command:'));
  }

}
