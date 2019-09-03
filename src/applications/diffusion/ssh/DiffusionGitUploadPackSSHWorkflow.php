<?php

final class DiffusionGitUploadPackSSHWorkflow
  extends DiffusionGitSSHWorkflow {

  private $requestAttempts = 0;
  private $requestFailures = 0;

  protected function didConstruct() {
    $this->setName('git-upload-pack');
    $this->setArguments(
      array(
        array(
          'name'      => 'dir',
          'wildcard'  => true,
        ),
      ));
  }

  protected function executeRepositoryOperations() {
    $is_proxy = $this->shouldProxy();
    if ($is_proxy) {
      return $this->executeRepositoryProxyOperations();
    }

    $viewer = $this->getSSHUser();
    $repository = $this->getRepository();
    $device = AlmanacKeys::getLiveDevice();

    $skip_sync = $this->shouldSkipReadSynchronization();

    $command = csprintf('git-upload-pack -- %s', $repository->getLocalPath());
    if (!$skip_sync) {
      $cluster_engine = id(new DiffusionRepositoryClusterEngine())
        ->setViewer($viewer)
        ->setRepository($repository)
        ->setLog($this)
        ->synchronizeWorkingCopyBeforeRead();

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Cleared to fetch on cluster host \"%s\".\n",
            $device->getName()));
      }
    }

    $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

    $pull_event = $this->newPullEvent();

    $future = id(new ExecFuture('%C', $command))
      ->setEnv($this->getEnvironment());

    $log = $this->newProtocolLog($is_proxy);
    if ($log) {
      $this->setProtocolLog($log);
      $log->didStartSession($command);
    }

    if (PhabricatorEnv::getEnvConfig('phabricator.show-prototypes')) {
      $protocol = new DiffusionGitUploadPackWireProtocol();
      if ($log) {
        $protocol->setProtocolLog($log);
      }
      $this->setWireProtocol($protocol);
    }

    $err = $this->newPassthruCommand()
      ->setIOChannel($this->getIOChannel())
      ->setCommandChannelFromExecFuture($future)
      ->execute();

    if ($log) {
      $log->didEndSession();
    }

    if ($err) {
      $pull_event
        ->setResultType(PhabricatorRepositoryPullEvent::RESULT_ERROR)
        ->setResultCode($err);
    } else {
      $pull_event
        ->setResultType(PhabricatorRepositoryPullEvent::RESULT_PULL)
        ->setResultCode(0);
    }

    $pull_event->save();

    if (!$err) {
      $this->waitForGitClient();
    }

    return $err;
  }

  private function executeRepositoryProxyOperations() {
    $device = AlmanacKeys::getLiveDevice();
    $for_write = false;

    $refs = $this->getAlmanacServiceRefs($for_write);
    $err = 1;

    while (true) {
      $ref = head($refs);

      $command = $this->getProxyCommandForServiceRef($ref);

      if ($device) {
        $this->writeClusterEngineLogMessage(
          pht(
            "# Fetch received by \"%s\", forwarding to cluster host \"%s\".\n",
            $device->getName(),
            $ref->getDeviceName()));
      }

      $command = PhabricatorDaemon::sudoCommandAsDaemonUser($command);

      $future = id(new ExecFuture('%C', $command))
        ->setEnv($this->getEnvironment());

      $this->didBeginRequest();

      $err = $this->newPassthruCommand()
        ->setIOChannel($this->getIOChannel())
        ->setCommandChannelFromExecFuture($future)
        ->execute();

      // TODO: Currently, when proxying, we do not write an event log on the
      // proxy. Perhaps we should write a "proxy log". This is not very useful
      // for statistics or auditing, but could be useful for diagnostics.
      // Marking the proxy logs as proxied (and recording devicePHID on all
      // logs) would make differentiating between these use cases easier.

      if (!$err) {
        $this->waitForGitClient();
        return $err;
      }

      // Throw away this service: the request failed and we're treating the
      // failure as persistent, so we don't want to retry another request to
      // the same host.
      array_shift($refs);

      $should_retry = $this->shouldRetryRequest($refs);
      if (!$should_retry) {
        return $err;
      }

      // If we haven't bailed out yet, we'll retry the request with the next
      // service.
    }

    throw new Exception(pht('Reached an unreachable place.'));
  }

  private function didBeginRequest() {
    $this->requestAttempts++;
    return $this;
  }

  private function shouldRetryRequest(array $remaining_refs) {
    $this->requestFailures++;

    if ($this->requestFailures > $this->requestAttempts) {
      throw new Exception(
        pht(
          "Workflow has recorded more failures than attempts; there is a ".
          "missing call to \"didBeginRequest()\".\n"));
    }

    if (!$remaining_refs) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# All available services failed to serve the request, ".
          "giving up.\n"));
      return false;
    }

    $read_len = $this->getIOBytesRead();
    if ($read_len) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# Client already read from service (%s bytes), unable to retry.\n",
          new PhutilNumber($read_len)));
      return false;
    }

    $write_len = $this->getIOBytesWritten();
    if ($write_len) {
      $this->writeClusterEngineLogMessage(
        pht(
          "# Client already wrote to service (%s bytes), unable to retry.\n",
          new PhutilNumber($write_len)));
      return false;
    }

    $this->writeClusterEngineLogMessage(
      pht(
        "# Service request failed, retrying (making attempt %s of %s).\n",
        new PhutilNumber($this->requestAttempts + 1),
        new PhutilNumber($this->requestAttempts + count($remaining_refs))));

    return true;
  }

}
