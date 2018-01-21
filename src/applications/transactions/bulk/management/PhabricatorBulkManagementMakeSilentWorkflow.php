<?php

final class PhabricatorBulkManagementMakeSilentWorkflow
  extends PhabricatorBulkManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('make-silent')
      ->setExamples('**make-silent** [options]')
      ->setSynopsis(
        pht('Configure a bulk job to execute silently.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht(
              'Configure bulk job __id__ to run silently (without sending '.
              'mail or publishing notifications).'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $id = $args->getArg('id');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht('Use "--id" to choose a bulk job to make silent.'));
    }

    $job = id(new PhabricatorWorkerBulkJobQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$job) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load bulk job with ID "%s".',
          $id));
    }

    if ($job->getIsSilent()) {
      echo tsprintf(
        "%s\n",
        pht('This job is already configured to run silently.'));
      return 0;
    }

    if ($job->getStatus() !== PhabricatorWorkerBulkJob::STATUS_CONFIRM) {
      throw new PhutilArgumentUsageException(
        pht(
          'Work has already started on job "%s". Jobs can not be '.
          'reconfigured after they have been started.',
          $id));
    }

    $job
      ->setIsSilent(true)
      ->save();

    echo tsprintf(
      "%s\n",
      pht(
        'Configured job "%s" to run silently.',
        $id));

    return 0;
  }

}
