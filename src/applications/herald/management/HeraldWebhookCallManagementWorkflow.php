<?php

final class HeraldWebhookCallManagementWorkflow
  extends HeraldWebhookManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('call')
      ->setExamples('**call** --id __id__ [--object __object__]')
      ->setSynopsis(pht('Call a webhook.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('Webhook ID to call'),
          ),
          array(
            'name' => 'object',
            'param' => 'object',
            'help' => pht('Submit transactions for a particular object.'),
          ),
          array(
            'name' => 'silent',
            'help' => pht('Set the "silent" flag on the request.'),
          ),
          array(
            'name' => 'secure',
            'help' => pht('Set the "secure" flag on the request.'),
          ),
          array(
            'name' => 'count',
            'param' => 'N',
            'help' => pht('Make a total of __N__ copies of the call.'),
          ),
          array(
            'name' => 'background',
            'help' => pht(
              'Instead of making calls in the foreground, add the tasks '.
              'to the daemon queue.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $id = $args->getArg('id');
    if (!$id) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a webhook to call with "--id".'));
    }

    $count = $args->getArg('count');
    if ($count === null) {
      $count = 1;
    }

    if ($count <= 0) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specified "--count" must be larger than 0.'));
    }

    $hook = id(new HeraldWebhookQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$hook) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to load specified webhook ("%s").',
          $id));
    }

    $object_name = $args->getArg('object');
    if ($object_name === null) {
      $object = $hook;
    } else {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withNames(array($object_name))
        ->execute();
      if (!$objects) {
        throw new PhutilArgumentUsageException(
          pht(
            'Unable to load specified object ("%s").',
            $object_name));
      }
      $object = head($objects);
    }

    $is_background = $args->getArg('background');

    $xaction_query =
      PhabricatorApplicationTransactionQuery::newQueryForObject($object);

    $xactions = $xaction_query
      ->withObjectPHIDs(array($object->getPHID()))
      ->setViewer($viewer)
      ->setLimit(10)
      ->execute();

    $application_phid = id(new PhabricatorHeraldApplication())->getPHID();

    if ($is_background) {
      echo tsprintf(
        "%s\n",
        pht(
          'Queueing webhook calls...'));
      $progress_bar = id(new PhutilConsoleProgressBar())
        ->setTotal($count);
    } else {
      echo tsprintf(
        "%s\n",
        pht(
          'Calling webhook...'));
      PhabricatorWorker::setRunAllTasksInProcess(true);
    }

    for ($ii = 0; $ii < $count; $ii++) {
      $request = HeraldWebhookRequest::initializeNewWebhookRequest($hook)
        ->setObjectPHID($object->getPHID())
        ->setIsTestAction(true)
        ->setIsSilentAction((bool)$args->getArg('silent'))
        ->setIsSecureAction((bool)$args->getArg('secure'))
        ->setTriggerPHIDs(array($application_phid))
        ->setTransactionPHIDs(mpull($xactions, 'getPHID'))
        ->save();

      $request->queueCall();

      if ($is_background) {
        $progress_bar->update(1);
      } else {
        $request->reload();

        echo tsprintf(
          "%s\n",
          pht(
            'Success, got HTTP %s from webhook.',
            $request->getErrorCode()));
      }
    }

    if ($is_background) {
      $progress_bar->done();
    }

    return 0;
  }

}
