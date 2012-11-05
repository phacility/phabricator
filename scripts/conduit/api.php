#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$time_start = microtime(true);

if ($argc !== 3) {
  echo "usage: api.php <user_phid> <method>\n";
  exit(1);
}

$user     = null;
$user_str = $argv[1];
try {
  $user = id(new PhabricatorUser())
    ->loadOneWhere('phid = %s', $user_str);
} catch (Exception $e) {
  // no op; we'll error in a line or two
}
if (empty($user)) {
  echo "usage: api.php <user_phid> <method>\n" .
       "user {$user_str} does not exist or failed to load\n";
  exit(1);
}

$method           = $argv[2];
$method_class_str = ConduitAPIMethod::getClassNameFromAPIMethodName($method);
try {
  $method_class = newv($method_class_str, array());
} catch (Exception $e) {
  echo "usage: api.php <user_phid> <method>\n" .
       "method {$method_class_str} does not exist\n";
  exit(1);
}
$log = new PhabricatorConduitMethodCallLog();
$log->setMethod($method);

$params = @file_get_contents('php://stdin');
$params = json_decode($params, true);
if (!is_array($params)) {
  echo "provide method parameters on stdin as a JSON blob";
  exit(1);
}

// build a quick ConduitAPIRequest from stdin PLUS the authenticated user
$conduit_request = new ConduitAPIRequest($params);
$conduit_request->setUser($user);

try {
  $result = $method_class->executeMethod($conduit_request);
  $error_code = null;
  $error_info = null;
} catch (ConduitException $ex) {
  $result = null;
  $error_code = $ex->getMessage();
  if ($ex->getErrorDescription()) {
    $error_info = $ex->getErrorDescription();
  } else {
    $error_info = $method_handler->getErrorDescription($error_code);
  }
}
$time_end = microtime(true);

$response = id(new ConduitAPIResponse())
  ->setResult($result)
  ->setErrorCode($error_code)
  ->setErrorInfo($error_info);
echo json_encode($response->toDictionary()), "\n";

// TODO -- how get $connection_id from SSH?
$connection_id = null;
$log->setConnectionID($connection_id);
$log->setError((string)$error_code);
$log->setDuration(1000000 * ($time_end - $time_start));
$log->save();

exit();
