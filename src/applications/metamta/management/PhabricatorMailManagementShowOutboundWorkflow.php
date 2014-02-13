<?php

final class PhabricatorMailManagementShowOutboundWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('show-outbound')
      ->setSynopsis('Show diagnostic details about outbound mail.')
      ->setExamples(
        "**show-outbound** --id 1 --id 2")
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => 'Show details about outbound mail with given ID.',
            'repeat'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        "Use the '--id' flag to specify one or more messages to show.");
    }

    $messages = id(new PhabricatorMetaMTAMail())->loadAllWhere(
      'id IN (%Ld)',
      $ids);

    if ($ids) {
      $ids = array_fuse($ids);
      $missing = array_diff_key($ids, $messages);
      if ($missing) {
        throw new PhutilArgumentUsageException(
          "Some specified messages do not exist: ".
          implode(', ', array_keys($missing)));
      }
    }

    $last_key = last_key($messages);
    foreach ($messages as $message_key => $message) {
      $info = array();

      $info[] = pht('PROPERTIES');
      $info[] = pht('ID: %d', $message->getID());
      $info[] = pht('Status: %s', $message->getStatus());
      $info[] = pht('Related PHID: %s', $message->getRelatedPHID());
      $info[] = pht('Message: %s', $message->getMessage());

      $info[] = null;
      $info[] = pht('PARAMETERS');
      $parameters = $message->getParameters();
      foreach ($parameters as $key => $value) {
        if ($key == 'body') {
          continue;
        }

        if ($key == 'headers') {
          continue;
        }

        if ($key == 'attachments') {
          continue;
        }

        if (!is_scalar($value)) {
          $value = json_encode($value);
        }

        $info[] = pht('%s: %s', $key, $value);
      }

      $info[] = null;
      $info[] = pht('HEADERS');
      foreach (idx($parameters, 'headers', array()) as $header) {
        list($name, $value) = $header;
        $info[] = "{$name}: {$value}";
      }

      $attachments = idx($parameters, 'attachments');
      if ($attachments) {
        $info[] = null;
        $info[] = pht('ATTACHMENTS');
        foreach ($attachments as $attachment) {
          $info[] = idx($attachment, 'filename', pht('Unnamed File'));
        }
      }

      $actors = $message->loadAllActors();
      $actors = array_select_keys(
        $actors,
        array_merge($message->getToPHIDs(), $message->getCcPHIDs()));
      $info[] = null;
      $info[] = pht('RECIPIENTS');
      foreach ($actors as $actor) {
        if ($actor->isDeliverable()) {
          $info[] = '  '.coalesce($actor->getName(), $actor->getPHID());
        } else {
          $info[] = '! '.coalesce($actor->getName(), $actor->getPHID());
          foreach ($actor->getUndeliverableReasons() as $reason) {
            $info[] = '    - '.$reason;
          }
        }
      }

      $info[] = null;
      $info[] = pht('BODY');
      $info[] = $message->getBody();

      $console->writeOut('%s', implode("\n", $info));

      if ($message_key != $last_key) {
        $console->writeOut("\n%s\n\n", str_repeat('-', 80));
      }
    }
  }

}
