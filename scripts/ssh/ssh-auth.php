#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$cert = file_get_contents('php://stdin');

if (!$cert) {
  exit(1);
}

$parts = preg_split('/\s+/', $cert);
if (count($parts) < 2) {
  exit(1);
}

list($type, $body) = $parts;

$user_dao = new PhabricatorUser();
$ssh_dao = new PhabricatorUserSSHKey();
$conn_r = $user_dao->establishConnection('r');

$row = queryfx_one(
  $conn_r,
  'SELECT userName FROM %T u JOIN %T ssh ON u.phid = ssh.userPHID
    WHERE ssh.keyType = %s AND ssh.keyBody = %s',
  $user_dao->getTableName(),
  $ssh_dao->getTableName(),
  $type,
  $body);

if (!$row) {
  exit(1);
}

$user = idx($row, 'userName');

if (!$user) {
  exit(1);
}

if (!PhabricatorUser::validateUsername($user)) {
  exit(1);
}

$bin = $root.'/bin/ssh-exec';
$cmd = csprintf('%s --phabricator-ssh-user %s', $bin, $user);
// This is additional escaping for the SSH 'command="..."' string.
$cmd = str_replace('"', '\\"', $cmd);

$options = array(
  'command="'.$cmd.'"',
  'no-port-forwarding',
  'no-X11-forwarding',
  'no-agent-forwarding',
  'no-pty',
);

echo implode(',', $options);
exit(0);
