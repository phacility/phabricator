#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('test client for Aphlict server');
$args->setSynopsis(<<<EOHELP
**aphlict_test_client.php** [__options__]
    Connect to the Aphlict server configured in the Phabricator config.

EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'    => 'server',
      'param'   => 'uri',
      'default' => PhabricatorEnv::getEnvConfig('notification.client-uri'),
      'help'    => 'Connect to __uri__ instead of the default server.',
    ),
  ));
$console = PhutilConsole::getConsole();

$errno = null;
$errstr = null;

$uri = $args->getArg('server');
$uri = new PhutilURI($uri);
$uri->setProtocol('tcp');

$console->writeErr("Connecting...\n");
$socket = stream_socket_client(
  $uri,
  $errno,
  $errstr);

if (!$socket) {
  $console->writeErr(
    "Unable to connect to Aphlict (at '$uri'). Error #{$errno}: {$errstr}");
  exit(1);
} else {
  $console->writeErr("Connected.\n");
}

$io_channel = new PhutilSocketChannel($socket);
$proto_channel = new PhutilJSONProtocolChannel($io_channel);

$json = new PhutilJSON();
while (true) {
  $message = $proto_channel->waitForMessage();
  $console->writeOut($json->encodeFormatted($message));
}

