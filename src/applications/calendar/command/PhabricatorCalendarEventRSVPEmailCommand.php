<?php

final class PhabricatorCalendarEventRSVPEmailCommand
  extends PhabricatorCalendarEventEmailCommand {

  public function getCommand() {
    return 'rsvp';
  }

  public function getCommandSyntax() {
    return '**!rsvp** //rsvp//';
  }

  public function getCommandSummary() {
    return pht('RSVP to event.');
  }

  public function getCommandDescription() {
    $status_attending = PhabricatorCalendarEventInvitee::STATUS_ATTENDING;
    $status_declined = PhabricatorCalendarEventInvitee::STATUS_DECLINED;

    $yes_values = implode(', ', $this->getYesValues());
    $no_values = implode(', ', $this->getNoValues());

    $table = array();
    $table[] = '| '.pht('RSVP').' | '.pht('Keywords');
    $table[] = '|---|---|';
    $table[] = '| '.$status_attending.' | '.$yes_values;
    $table[] = '| '.$status_declined.' | '.$no_values;
    $table = implode("\n", $table);

    return pht(
      'To RSVP to the event, specify the desired RSVP, like '.
      '`!rsvp yes`. This table shows the configured names for rsvp\'s.'.
      "\n\n%s\n\n".
      'If you specify an invalid rsvp, the command is ignored. This '.
      'command has no effect if you do not specify an rsvp.',
      $table);
  }

  public function buildTransactions(
    PhabricatorUser $viewer,
    PhabricatorApplicationTransactionInterface $object,
    PhabricatorMetaMTAReceivedMail $mail,
    $command,
    array $argv) {

    $target = phutil_utf8_strtolower(implode(' ', $argv));

    $yes_values = $this->getYesValues();
    $no_values = $this->getNoValues();

    if (in_array($target, $yes_values)) {
      $rsvp = PhabricatorCalendarEventAcceptTransaction::TRANSACTIONTYPE;
    } else if (in_array($target, $no_values)) {
      $rsvp = PhabricatorCalendarEventDeclineTransaction::TRANSACTIONTYPE;
    } else {
      return array();
    }

    $xactions = array();
    $xactions[] = $object->getApplicationTransactionTemplate()
      ->setTransactionType($rsvp)
      ->setNewValue(true);
    return $xactions;
  }

  private function getYesValues() {
    return array(
      'yes',
      'yep',
      'sounds good',
      'going',
      'attending',
      'will be there',
      'sure',
      'accept',
      'ya',
      'yeah',
      'yuh',
      'uhuh',
      'ok',
      'okay',
      'yiss',
      'aww yiss',
      'attend',
      'intend to attend',
      'confirm',
      'confirmed',
      'bringing dessert',
      'bringing desert',
      'time2business',
      'time4business',
      );
  }

  private function getNoValues() {
    return array(
      'no',
      'nope',
      'no thank you',
      'next time',
      'nah',
      'nuh',
      'huh',
      'wut',
      'no way',
      'nuhuh',
      'decline',
      'declined',
      'leave',
      'cancel',
      );
  }

}
