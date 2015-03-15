<?php

final class PhabricatorPhortuneManagementInvoiceWorkflow
  extends PhabricatorPhortuneManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('invoice')
      ->setSynopsis(
        pht(
          'Invoices a subscription for a given billing period. This can '.
          'charge payment accounts twice.'))
      ->setArguments(
        array(
          array(
            'name' => 'subscription',
            'param' => 'phid',
            'help' => pht('Subscription to invoice.'),
          ),
          array(
            'name' => 'now',
            'param' => 'time',
            'help' => pht(
              'Bill as though the current time is a specific time.'),
          ),
          array(
            'name' => 'last',
            'param' => 'time',
            'help' => pht('Set the start of the billing period.'),
          ),
          array(
            'name' => 'next',
            'param' => 'time',
            'help' => pht('Set the end of the billing period.'),
          ),
          array(
            'name' => 'auto-range',
            'help' => pht('Automatically use the current billing period.'),
          ),
          array(
            'name' => 'force',
            'help' => pht(
              'Skip the prompt warning you that this operation is '.
              'potentially dangerous.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $subscription_phid = $args->getArg('subscription');
    if (!$subscription_phid) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify which subscription to invoice with --subscription.'));
    }

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($subscription_phid))
      ->needTriggers(true)
      ->executeOne();
    if (!$subscription) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load subscription with PHID "%s".',
          $subscription_phid));
    }

    $now = $args->getArg('now');
    $now = $this->parseTimeArgument($now);
    if (!$now) {
      $now = PhabricatorTime::getNow();
    }

    $time_guard = PhabricatorTime::pushTime($now, date_default_timezone_get());

    $console->writeOut(
      "%s\n",
      pht(
        'Set current time to %s.',
        phabricator_datetime(PhabricatorTime::getNow(), $viewer)));

    $auto_range = $args->getArg('auto-range');
    $last_arg = $args->getArg('last');
    $next_arg = $args->getARg('next');

    if (!$auto_range && !$last_arg && !$next_arg) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a billing range with --last and --next, or use '.
          '--auto-range.'));
    } else if (!$auto_range & (!$last_arg || !$next_arg)) {
      throw new PhutilArgumentUsageException(
        pht(
          'When specifying --last or --next, you must specify both arguments '.
          'to define the beginning and end of the billing range.'));
    } else if (!$auto_range && ($last_arg && $next_arg)) {
      $last_time = $this->parseTimeArgument($args->getArg('last'));
      $next_time = $this->parseTimeArgument($args->getArg('next'));
    } else if ($auto_range && ($last_arg || $next_arg)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use either --auto-range or --last and --next to specify the '.
          'billing range, but not both.'));
    } else {
      $trigger = $subscription->getTrigger();
      $event = $trigger->getEvent();
      if (!$event) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to calculate --auto-range, this subscription has not been '.
            'scheduled for billing yet. Wait for the trigger daemon to '.
            'schedule the subscription.'));
      }
      $last_time = $event->getLastEventEpoch();
      $next_time = $event->getNextEventEpoch();
    }

    $console->writeOut(
      "%s\n",
      pht(
        'Preparing to invoice subscription "%s" from %s to %s.',
        $subscription->getSubscriptionName(),
        ($last_time
          ? phabricator_datetime($last_time, $viewer)
          : pht('subscription creation')),
        phabricator_datetime($next_time, $viewer)));

    PhabricatorWorker::setRunAllTasksInProcess(true);

    if (!$args->getArg('force')) {
      $console->writeOut(
        "**<bg:yellow> %s </bg>**\n%s\n",
        pht('WARNING'),
        phutil_console_wrap(
          pht(
            'Manually invoicing will double bill payment accounts if the '.
            'range overlaps an existing or future invoice. This script is '.
            'intended for testing and development, and should not be part '.
            'of routine billing operations. If you continue, you may '.
            'incorrectly overcharge customers.')));

      if (!phutil_console_confirm(pht('Really invoice this subscription?'))) {
        throw new Exception(pht('Declining to invoice.'));
      }
    }

    PhabricatorWorker::scheduleTask(
      'PhortuneSubscriptionWorker',
      array(
        'subscriptionPHID' => $subscription->getPHID(),
        'trigger.last-epoch' => $last_time,
        'trigger.this-epoch' => $next_time,
        'manual' => true,
      ),
      array(
        'objectPHID' => $subscription->getPHID(),
      ));

    return 0;
  }

}
