<?php

final class PhabricatorMailManagementShowInboundWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('show-inbound')
      ->setSynopsis(pht('Show diagnostic details about inbound mail.'))
      ->setExamples(
        '**show-inbound** --id 1 --id 2')
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => pht('Show details about inbound mail with given ID.'),
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
          "Use the '%s' flag to specify one or more messages to show.",
          '--id'));
    }

    $messages = id(new PhabricatorMetaMTAReceivedMail())->loadAllWhere(
      'id IN (%Ld)',
      $ids);

    if ($ids) {
      $ids = array_fuse($ids);
      $missing = array_diff_key($ids, $messages);
      if ($missing) {
        throw new PhutilArgumentUsageException(
          pht(
            'Some specified messages do not exist: %s',
            implode(', ', array_keys($missing))));
      }
    }

    $last_key = last_key($messages);
    foreach ($messages as $message_key => $message) {
      $info = array();

      $info[] = pht('PROPERTIES');
      $info[] = pht('ID: %d', $message->getID());
      $info[] = pht('Status: %s', $message->getStatus());
      $info[] = pht('Related PHID: %s', $message->getRelatedPHID());
      $info[] = pht('Author PHID: %s', $message->getAuthorPHID());
      $info[] = pht('Message ID Hash: %s', $message->getMessageIDHash());

      if ($message->getMessage()) {
        $info[] = null;
        $info[] = pht('MESSAGE');
        $info[] = $message->getMessage();
      }

      $info[] = null;
      $info[] = pht('HEADERS');
      foreach ($message->getHeaders() as $key => $value) {
        if (is_array($value)) {
          $value = implode("\n", $value);
        }
        $info[] = pht('%s: %s', $key, $value);
      }

      $bodies = $message->getBodies();

      $last_body = last_key($bodies);

      $info[] = null;
      $info[] = pht('BODIES');
      foreach ($bodies as $key => $value) {
        $info[] = pht('Body "%s"', $key);
        $info[] = $value;
        if ($key != $last_body) {
          $info[] = null;
        }
      }

      $attachments = $message->getAttachments();

      $info[] = null;
      $info[] = pht('ATTACHMENTS');
      if (!$attachments) {
        $info[] = pht('No attachments.');
      } else {
        foreach ($attachments as $attachment) {
          $info[] = pht('File PHID: %s', $attachment);
        }
      }

      $console->writeOut("%s\n", implode("\n", $info));

      if ($message_key != $last_key) {
        $console->writeOut("\n%s\n\n", str_repeat('-', 80));
      }
    }
  }

}
