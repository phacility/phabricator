<?php

final class HeraldWebhookCallManagementWorkflow
  extends HeraldWebhookManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('call')
      ->setExamples('**call** --id __id__')
      ->setSynopsis(pht('Call a webhook.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'help' => pht('Webhook ID to call'),
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

    $object = $hook;

    $application_phid = id(new PhabricatorHeraldApplication())->getPHID();

    $request = HeraldWebhookRequest::initializeNewWebhookRequest($hook)
      ->setObjectPHID($object->getPHID())
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
