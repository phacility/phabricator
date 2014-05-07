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
   * containing a clean body text (e.g. "taking this task"), a $command
   * (e.g. !claim, !assign) and a $command_value (e.g. "epriestley" in the
   * !assign example.)
   *
   * @return dict
   */
  public function parseBody($body) {
    $body = $this->stripTextBody($body);
    $lines = explode("\n", trim($body));
    $first_line = head($lines);

    $command = null;
    $command_value = null;
    $matches = null;
    if (preg_match('/^!(\w+)\s*(\S+)?/', $first_line, $matches)) {
      $lines = array_slice($lines, 1);
      $body = implode("\n", $lines);
      $body = trim($body);

      $command = $matches[1];
      $command_value = idx($matches, 2);
    }

    return array(
      'body' => $body,
      'command' => $command,
      'command_value' => $command_value);
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
