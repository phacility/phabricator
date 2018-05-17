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

    $xaction_query =
      PhabricatorApplicationTransactionQuery::newQueryForObject($object);

    $xactions = $xaction_query
      ->withObjectPHIDs(array($object->getPHID()))
      ->setViewer($viewer)
      ->setLimit(10)
      ->execute();

    $application_phid = id(new PhabricatorHeraldApplication())->getPHID();

    $request = HeraldWebhookRequest::initializeNewWebhookRequest($hook)
      ->setObjectPHID($object->getPHID())
      ->setIsTestAction(true)
      ->setIsSilentAction((bool)$args->getArg('silent'))
      ->setIsSecureAction((bool)$args->getArg('secure'))
      ->setTriggerPHIDs(array($application_phid))
      ->setTransactionPHIDs(mpull($xactions, 'getPHID'))
      ->save();

    PhabricatorWorker::setRunAllTasksInProcess(true);
    $request->queueCall();

    $request->reload();

    echo tsprintf(
      "%s\n",
      pht(
        'Success, got HTTP %s from webhook.',
        $request->getErrorCode()));

    return 0;
  }

}
