<?php

final class PhabricatorMailManagementShowOutboundWorkflow
  extends PhabricatorMailManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('show-outbound')
      ->setSynopsis(pht('Show diagnostic details about outbound mail.'))
      ->setExamples(
        '**show-outbound** --id 1 --id 2')
      ->setArguments(
        array(
          array(
            'name'    => 'id',
            'param'   => 'id',
            'help'    => pht('Show details about outbound mail with given ID.'),
            'repeat'  => true,
          ),
          array(
            'name' => 'dump-html',
            'help' => pht(
              'Dump the HTML body of the mail. You can redirect it to a '.
              'file and then open it in a browser.'),
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

    foreach ($ids as $id) {
      if (!ctype_digit($id)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Argument "%s" is not a valid message ID.',
            $id));
      }
    }

    $messages = id(new PhabricatorMetaMTAMail())->loadAllWhere(
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
      if ($args->getArg('dump-html')) {
        $html_body = $message->getHTMLBody();
        if (strlen($html_body)) {
          $template =
            "<!doctype html><html><body>{$html_body}</body></html>";
          $console->writeOut("%s\n", $html_body);
        } else {
          $console->writeErr(
            "%s\n",
            pht('(This message has no HTML body.)'));
        }
        continue;
      }

      $info = array();

      $info[] = $this->newSectionHeader(pht('PROPERTIES'));
      $info[] = pht('ID: %d', $message->getID());
      $info[] = pht('Status: %s', $message->getStatus());
      $info[] = pht('Related PHID: %s', $message->getRelatedPHID());
      $info[] = pht('Message: %s', $message->getMessage());

      $ignore = array(
        'body' => true,
        'body.sent' => true,
        'html-body' => true,
        'headers' => true,
        'attachments' => true,
        'headers.sent' => true,
        'headers.unfiltered' => true,
        'authors.sent' => true,
      );

      $info[] = null;
      $info[] = $this->newSectionHeader(pht('PARAMETERS'));
      $parameters = $message->getParameters();
      foreach ($parameters as $key => $value) {
        if (isset($ignore[$key])) {
          continue;
        }

        if (!is_scalar($value)) {
          $value = json_encode($value);
        }

        $info[] = pht('%s: %s', $key, $value);
      }

      $info[] = null;
      $info[] = $this->newSectionHeader(pht('HEADERS'));

      $headers = $message->getDeliveredHeaders();
      if (!$headers) {
        $headers = array();
      }

      $unfiltered = $message->getUnfilteredHeaders();
      if (!$unfiltered) {
        $unfiltered = array();
      }

      $header_map = array();
      foreach ($headers as $header) {
        list($name, $value) = $header;
        $header_map[$name.':'.$value] = true;
      }

      foreach ($unfiltered as $header) {
        list($name, $value) = $header;
        $was_sent = isset($header_map[$name.':'.$value]);

        if ($was_sent) {
          $marker = ' ';
        } else {
          $marker = '#';
        }

        $info[] = "{$marker} {$name}: {$value}";
      }

      $attachments = idx($parameters, 'attachments');
      if ($attachments) {
        $info[] = null;

        $info[] = $this->newSectionHeader(pht('ATTACHMENTS'));

        foreach ($attachments as $attachment) {
          $info[] = idx($attachment, 'filename', pht('Unnamed File'));
        }
      }

      $all_actors = $message->loadAllActors();

      $actors = $message->getDeliveredActors();
      if ($actors) {
        $info[] = null;

        $info[] = $this->newSectionHeader(pht('RECIPIENTS'));

        foreach ($actors as $actor_phid => $actor_info) {
          $actor = idx($all_actors, $actor_phid);
          if ($actor) {
            $actor_name = coalesce($actor->getName(), $actor_phid);
          } else {
            $actor_name = $actor_phid;
          }

          $deliverable = $actor_info['deliverable'];
          if ($deliverable) {
            $info[] = '  '.$actor_name;
          } else {
            $info[] = '! '.$actor_name;
          }

          $reasons = $actor_info['reasons'];
          foreach ($reasons as $reason) {
            $name = PhabricatorMetaMTAActor::getReasonName($reason);
            $desc = PhabricatorMetaMTAActor::getReasonDescription($reason);
            $info[] = '    - '.$name.': '.$desc;
          }
        }
      }

      $info[] = null;
      $info[] = $this->newSectionHeader(pht('TEXT BODY'));
      if (strlen($message->getBody())) {
        $info[] = tsprintf('%B', $message->getBody());
      } else {
        $info[] = pht('(This message has no text body.)');
      }

      $delivered_body = $message->getDeliveredBody();
      if ($delivered_body !== null) {
        $info[] = null;
        $info[] = $this->newSectionHeader(pht('BODY AS DELIVERED'), true);
        $info[] = tsprintf('%B', $delivered_body);
      }

      $info[] = null;
      $info[] = $this->newSectionHeader(pht('HTML BODY'));
      if (strlen($message->getHTMLBody())) {
        $info[] = $message->getHTMLBody();
        $info[] = null;
      } else {
        $info[] = pht('(This message has no HTML body.)');
        $info[] = null;
      }

      $console->writeOut('%s', implode("\n", $info));

      if ($message_key != $last_key) {
        $console->writeOut("\n%s\n\n", str_repeat('-', 80));
      }
    }
  }

  private function newSectionHeader($label, $emphasize = false) {
    if ($emphasize) {
      return tsprintf('**<bg:yellow> %s </bg>**', $label);
    } else {
      return tsprintf('**<bg:blue> %s </bg>**', $label);
    }
  }

}
