<?php

final class ConduitSSHWorkflow extends PhabricatorSSHWorkflow {

  protected function didConstruct() {
    $this->setName('conduit');
    $this->setArguments(
      array(
        array(
          'name'      => 'method',
          'wildcard'  => true,
        ),
      ));
  }

  public function execute(PhutilArgumentParser $args) {
    $time_start = microtime(true);

    $methodv = $args->getArg('method');
    if (!$methodv) {
      throw new Exception(pht('No Conduit method provided.'));
    } else if (count($methodv) > 1) {
      throw new Exception(pht('Too many Conduit methods provided.'));
    }

    $method = head($methodv);

    $json = $this->readAllInput();
    $raw_params = null;
    try {
      $raw_params = phutil_json_decode($json);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht('Invalid JSON input.'),
        $ex);
    }

    $params = idx($raw_params, 'params', '[]');
    $params = phutil_json_decode($params);
    $metadata = idx($params, '__conduit__', array());
    unset($params['__conduit__']);

    $call = null;
    $error_code = null;
    $error_info = null;

    try {
      $call = new ConduitCall($method, $params);
      $call->setUser($this->getUser());

      $result = $call->execute();
    } catch (ConduitException $ex) {
      $result = null;
      $error_code = $ex->getMessage();
      if ($ex->getErrorDescription()) {
        $error_info = $ex->getErrorDescription();
      } else if ($call) {
        $error_info = $call->getErrorDescription($error_code);
      }
    }

    $response = id(new ConduitAPIResponse())
      ->setResult($result)
      ->setErrorCode($error_code)
      ->setErrorInfo($error_info);

    $json_out = json_encode($response->toDictionary());
    $json_out = $json_out."\n";

    $this->getIOChannel()->write($json_out);

    // NOTE: Flush here so we can get an accurate result for the duration,
    // if the response is large and the receiver is slow to read it.
    $this->getIOChannel()->flush();

    $time_end = microtime(true);

    $connection_id = idx($metadata, 'connectionID');
    $log = id(new PhabricatorConduitMethodCallLog())
      ->setCallerPHID($this->getUser()->getPHID())
      ->setConnectionID($connection_id)
      ->setMethod($method)
      ->setError((string)$error_code)
      ->setDuration(1000000 * ($time_end - $time_start))
      ->save();
  }
}
