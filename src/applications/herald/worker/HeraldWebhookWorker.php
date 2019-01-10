<?php

final class HeraldWebhookWorker
  extends PhabricatorWorker {

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $data = $this->getTaskData();
    $request_phid = idx($data, 'webhookRequestPHID');

    $request = id(new HeraldWebhookRequestQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request_phid))
      ->executeOne();
    if (!$request) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load webhook request ("%s"). It may have been '.
          'garbage collected.',
          $request_phid));
    }

    $status = $request->getStatus();
    if ($status !== HeraldWebhookRequest::STATUS_QUEUED) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Webhook request ("%s") is not in "%s" status (actual '.
          'status is "%s"). Declining call to hook.',
          $request_phid,
          HeraldWebhookRequest::STATUS_QUEUED,
          $status));
    }

    // If we're in silent mode, permanently fail the webhook request and then
    // return to complete this task.
    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $this->failRequest(
        $request,
        HeraldWebhookRequest::ERRORTYPE_HOOK,
        HeraldWebhookRequest::ERROR_SILENT);
      return;
    }

    $hook = $request->getWebhook();

    if ($hook->isDisabled()) {
      $this->failRequest(
        $request,
        HeraldWebhookRequest::ERRORTYPE_HOOK,
        HeraldWebhookRequest::ERROR_DISABLED);
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Associated hook ("%s") for webhook request ("%s") is disabled.',
          $hook->getPHID(),
          $request_phid));
    }

    $uri = $hook->getWebhookURI();
    try {
      PhabricatorEnv::requireValidRemoteURIForFetch(
        $uri,
        array(
          'http',
          'https',
        ));
    } catch (Exception $ex) {
      $this->failRequest(
        $request,
        HeraldWebhookRequest::ERRORTYPE_HOOK,
        HeraldWebhookRequest::ERROR_URI);
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Associated hook ("%s") for webhook request ("%s") has invalid '.
          'fetch URI: %s',
          $hook->getPHID(),
          $request_phid,
          $ex->getMessage()));
    }

    $object_phid = $request->getObjectPHID();

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->executeOne();
    if (!$object) {
      $this->failRequest(
        $request,
        HeraldWebhookRequest::ERRORTYPE_HOOK,
        HeraldWebhookRequest::ERROR_OBJECT);

      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load object ("%s") for webhook request ("%s").',
          $object_phid,
          $request_phid));
    }

    $xaction_query = PhabricatorApplicationTransactionQuery::newQueryForObject(
      $object);
    $xaction_phids = $request->getTransactionPHIDs();
    if ($xaction_phids) {
      $xactions = $xaction_query
        ->setViewer($viewer)
        ->withObjectPHIDs(array($object_phid))
        ->withPHIDs($xaction_phids)
        ->execute();
      $xactions = mpull($xactions, null, 'getPHID');
    } else {
      $xactions = array();
    }

    // To prevent thundering herd issues for high volume webhooks (where
    // a large number of workers might try to work through a request backlog
    // simultaneously, before the error backoff can catch up), we never
    // parallelize requests to a particular webhook.

    $lock_key = 'webhook('.$hook->getPHID().')';
    $lock = PhabricatorGlobalLock::newLock($lock_key);

    try {
      $lock->lock();
    } catch (Exception $ex) {
      phlog($ex);
      throw new PhabricatorWorkerYieldException(15);
    }

    $caught = null;
    try {
      $this->callWebhookWithLock($hook, $request, $object, $xactions);
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $lock->unlock();

    if ($caught) {
      throw $caught;
    }
  }

  private function callWebhookWithLock(
    HeraldWebhook $hook,
    HeraldWebhookRequest $request,
    $object,
    array $xactions) {
    $viewer = PhabricatorUser::getOmnipotentUser();

    if ($hook->isInErrorBackoff($viewer)) {
      throw new PhabricatorWorkerYieldException($hook->getErrorBackoffWindow());
    }

    $xaction_data = array();
    foreach ($xactions as $xaction) {
      $xaction_data[] = array(
        'phid' => $xaction->getPHID(),
      );
    }

    $trigger_data = array();
    foreach ($request->getTriggerPHIDs() as $trigger_phid) {
      $trigger_data[] = array(
        'phid' => $trigger_phid,
      );
    }

    $payload = array(
      'object' => array(
        'type' => phid_get_type($object->getPHID()),
        'phid' => $object->getPHID(),
      ),
      'triggers' => $trigger_data,
      'action' => array(
        'test' => $request->getIsTestAction(),
        'silent' => $request->getIsSilentAction(),
        'secure' => $request->getIsSecureAction(),
        'epoch' => (int)$request->getDateCreated(),
      ),
      'transactions' => $xaction_data,
    );

    $payload = id(new PhutilJSON())->encodeFormatted($payload);
    $key = $hook->getHmacKey();
    $signature = PhabricatorHash::digestHMACSHA256($payload, $key);
    $uri = $hook->getWebhookURI();

    $future = id(new HTTPSFuture($uri))
      ->setMethod('POST')
      ->addHeader('Content-Type', 'application/json')
      ->addHeader('X-Phabricator-Webhook-Signature', $signature)
      ->setTimeout(15)
      ->setData($payload);

    list($status) = $future->resolve();

    if ($status->isTimeout()) {
      $error_type = HeraldWebhookRequest::ERRORTYPE_TIMEOUT;
    } else {
      $error_type = HeraldWebhookRequest::ERRORTYPE_HTTP;
    }
    $error_code = $status->getStatusCode();

    $request
      ->setErrorType($error_type)
      ->setErrorCode($error_code)
      ->setLastRequestEpoch(PhabricatorTime::getNow());

    $retry_forever = HeraldWebhookRequest::RETRY_FOREVER;
    if ($status->isTimeout() || $status->isError()) {
      $should_retry = ($request->getRetryMode() === $retry_forever);

      $request
        ->setLastRequestResult(HeraldWebhookRequest::RESULT_FAIL);

      if ($should_retry) {
        $request->save();

        throw new Exception(
          pht(
            'Webhook request ("%s", to "%s") failed (%s / %s). The request '.
            'will be retried.',
            $request->getPHID(),
            $uri,
            $error_type,
            $error_code));
      } else {
        $request
          ->setStatus(HeraldWebhookRequest::STATUS_FAILED)
          ->save();

        throw new PhabricatorWorkerPermanentFailureException(
          pht(
            'Webhook request ("%s", to "%s") failed (%s / %s). The request '.
            'will not be retried.',
            $request->getPHID(),
            $uri,
            $error_type,
            $error_code));
      }
    } else {
      $request
        ->setLastRequestResult(HeraldWebhookRequest::RESULT_OKAY)
        ->setStatus(HeraldWebhookRequest::STATUS_SENT)
        ->save();
    }
  }

  private function failRequest(
    HeraldWebhookRequest $request,
    $error_type,
    $error_code) {

    $request
      ->setStatus(HeraldWebhookRequest::STATUS_FAILED)
      ->setErrorType($error_type)
      ->setErrorCode($error_code)
      ->setLastRequestResult(HeraldWebhookRequest::RESULT_NONE)
      ->setLastRequestEpoch(0)
      ->save();
  }

}
