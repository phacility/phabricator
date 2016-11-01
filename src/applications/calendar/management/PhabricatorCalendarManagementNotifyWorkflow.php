<?php

final class PhabricatorCalendarManagementNotifyWorkflow
  extends PhabricatorCalendarManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('notify')
      ->setExamples('**notify** [options]')
      ->setSynopsis(
        pht(
          'Test and debug notifications about upcoming events.'))
      ->setArguments(
        array(
          array(
            'name' => 'minutes',
            'param' => 'N',
            'help' => pht(
              'Notify about events in the next __N__ minutes (default: 15). '.
              'Setting this to a larger value makes testing easier.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $engine = new PhabricatorCalendarNotificationEngine();

    $minutes = $args->getArg('minutes');
    if ($minutes) {
      $engine->setNotifyWindow(phutil_units("{$minutes} minutes in seconds"));
    }

    $engine->publishNotifications();

    return 0;
  }

}
