<?php

final class PhabricatorConfigManagementDoneWorkflow
  extends PhabricatorConfigManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('done')
      ->setExamples('**done** __activity__')
      ->setSynopsis(pht('Mark a manual upgrade activity as complete.'))
      ->setArguments(
        array(
          array(
            'name' => 'activities',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $activities = $args->getArg('activities');
    if (!$activities) {
      throw new PhutilArgumentUsageException(
        pht('Specify an activity to mark as completed.'));
    }

    foreach ($activities as $type) {
      $activity = id(new PhabricatorConfigManualActivity())->loadOneWhere(
        'activityType = %s',
        $type);
      if (!$activity) {
        throw new PhutilArgumentUsageException(
          pht(
            'Activity "%s" is not currently marked as required, so there '.
            'is no need to complete it.',
            $type));
      } else {
        $activity->delete();
        echo tsprintf(
          "%s\n",
          pht(
            'Marked activity "%s" as completed.',
            $type));
      }
    }

    echo tsprintf(
      "%s\n",
      pht('Done.'));

    return 0;
  }

}
