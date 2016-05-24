#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

try {
  $cert = file_get_contents('php://stdin');
  $public_key = PhabricatorAuthSSHPublicKey::newFromRawKey($cert);
} catch (Exception $ex) {
  exit(1);
}

$key = id(new PhabricatorAuthSSHKeyQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withKeys(array($public_key))
  ->withIsActive(true)
  ->executeOne();
if (!$key) {
  exit(1);
}

$object = $key->getObject();
if (!($object instanceof PhabricatorUser)) {
  exit(1);
}

$bin = $root.'/bin/ssh-exec';
$cmd = csprintf('%s --phabricator-ssh-user %s', $bin, $object->getUsername());
// This is additional escaping for the SSH 'command="..."' string.
$cmd = addcslashes($cmd, '"\\');

$options = array(
  'command="'.$cmd.'"',
  'no-port-forwarding',
  'no-X11-forwarding',
  'no-agent-forwarding',
  'no-pty',
);

echo implode(',', $options);
exit(0);
