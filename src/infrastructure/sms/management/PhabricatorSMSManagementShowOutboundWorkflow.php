<?php

final class PhabricatorSMSManagementShowOutboundWorkflow
  extends PhabricatorSMSManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('show-outbound')
      ->setSynopsis(pht('Show diagnostic details about outbound SMS.'))
      ->setExamples(
        '**show-outbound** --id 1 --id 2')
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => pht('Show details about outbound SMS with given ID.'),
            'repeat'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht(
          "Use the '%s' flag to specify one or more SMS messages to show.",
          '--id'));
    }

    $messages = id(new PhabricatorSMS())->loadAllWhere(
      'id IN (%Ld)',
      $ids);

    if ($ids) {
      $ids = array_fuse($ids);
      $missing = array_diff_key($ids, $messages);
      if ($missing) {
        throw new PhutilArgumentUsageException(
          pht(
            'Some specified SMS messages do not exist: %s',
            implode(', ', array_keys($missing))));
      }
    }

    $last_key = last_key($messages);
    foreach ($messages as $message_key => $message) {
      $info = array();

      $info[] = pht('PROPERTIES');
      $info[] = pht('ID: %d', $message->getID());
      $info[] = pht('Status: %s', $message->getSendStatus());
      $info[] = pht('To: %s', $message->getToNumber());
      $info[] = pht('From: %s', $message->getFromNumber());

      $info[] = null;
      $info[] = pht('BODY');
      $info[] = $message->getBody();
      $info[] = null;

      $console->writeOut('%s', implode("\n", $info));

      if ($message_key != $last_key) {
        $console->writeOut("\n%s\n\n", str_repeat('-', 80));
      }
    }
  }

}
