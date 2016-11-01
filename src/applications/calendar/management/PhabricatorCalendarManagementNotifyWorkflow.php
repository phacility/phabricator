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
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $engine = new PhabricatorCalendarNotificationEngine();
    $engine->publishNotifications();

    return 0;
  }

}
