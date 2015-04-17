<?php

final class PhabricatorMetaMTAEmailBodyParser {

  /**
   * Mails can have bodies such as
   *
   *   !claim
   *
   *   taking this task
   *
   * Or
   *
   *   !assign epriestley
   *
   *   please, take this task I took; its hard
   *
   * This function parses such an email body and returns a dictionary
   * containing a clean body text (e.g. "taking this task"), and a list of
   * commands. For example, this body above might parse as:
   *
   *   array(
   *     'body' => 'please, take this task I took; its hard',
   *     'commands' => array(
   *       array('assign', 'epriestley'),
   *     ),
   *   )
   *
   * @param   string  Raw mail text body.
   * @return  dict    Parsed body.
   */
  public function parseBody($body) {
    $body = $this->stripTextBody($body);

    $commands = array();

    $lines = phutil_split_lines($body, $retain_endings = true);

    // We'll match commands at the beginning and end of the mail, but not
    // in the middle of the mail body.
    list($top_commands, $lines) = $this->stripCommands($lines);
    list($end_commands, $lines) = $this->stripCommands(array_reverse($lines));
    $lines = array_reverse($lines);
    $commands = array_merge($top_commands, array_reverse($end_commands));

    $lines = rtrim(implode('', $lines));

    return array(
      'body' => $lines,
      'commands' => $commands,
    );
  }

  private function stripCommands(array $lines) {
    $saw_command = false;
    $commands = array();
    foreach ($lines as $key => $line) {
      if (!strlen(trim($line)) && $saw_command) {
        unset($lines[$key]);
        continue;
      }

      $matches = null;
      if (!preg_match('/^\s*!(\w+.*$)/', $line, $matches)) {
        break;
      }

      $arg_str = $matches[1];
      $argv = preg_split('/[,\s]+/', trim($arg_str));
      $commands[] = $argv;
      unset($lines[$key]);

      $saw_command = true;
    }

    return array($commands, $lines);
  }

  public function stripTextBody($body) {
    return trim($this->stripSignature($this->stripQuotedText($body)));
  }

  private function stripQuotedText($body) {

    // Look for "On <date>, <user> wrote:". This may be split across multiple
    // lines. We need to be careful not to remove all of a message like this:
    //
    //   On which day do you want to meet?
    //
    //   On <date>, <user> wrote:
    //   > Let's set up a meeting.

    $start = null;
    $lines = phutil_split_lines($body);
    foreach ($lines as $key => $line) {
      if (preg_match('/^\s*>?\s*On\b/', $line)) {
        $start = $key;
      }
      if ($start !== null) {
        if (preg_match('/\bwrote:/', $line)) {
          $lines = array_slice($lines, 0, $start);
          $body = implode('', $lines);
          break;
        }
      }
    }

    // Outlook english
    $body = preg_replace(
      '/^\s*(> )?-----Original Message-----.*?/imsU',
      '',
      $body);

    // Outlook danish
    $body = preg_replace(
      '/^\s*(> )?-----Oprindelig Meddelelse-----.*?/imsU',
      '',
      $body);

    // See example in T3217.
    $body = preg_replace(
      '/^________________________________________\s+From:.*?/imsU',
      '',
      $body);

    return rtrim($body);
  }

  private function stripSignature($body) {
    // Quasi-"standard" delimiter, for lols see:
    //   https://bugzilla.mozilla.org/show_bug.cgi?id=58406
    $body = preg_replace(
      '/^-- +$.*/sm',
      '',
      $body);

    // Mailbox seems to make an attempt to comply with the "standard" but
    // omits the leading newline and uses an em dash. This may or may not have
    // the trailing space, but it's unique enough that there's no real ambiguity
    // in detecting it.
    $body = preg_replace(
      "/\s*\xE2\x80\x94\s*\nSent from Mailbox\s*\z/su",
      '',
      $body);

    // HTC Mail application (mobile)
    $body = preg_replace(
      '/^\s*^Sent from my HTC smartphone.*/sm',
      '',
      $body);

    // Apple iPhone
    $body = preg_replace(
      '/^\s*^Sent from my iPhone\s*$.*/sm',
      '',
      $body);

    return rtrim($body);
  }

}
