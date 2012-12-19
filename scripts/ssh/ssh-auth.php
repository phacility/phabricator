#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$cert = file_get_contents('php://stdin');

$user = null;
if ($cert) {
  $user_dao = new PhabricatorUser();
  $ssh_dao = new PhabricatorUserSSHKey();
  $conn = $user_dao->establishConnection('r');

  list($type, $body) = array_merge(
    explode(' ', $cert),
    array('', ''));

  $row = queryfx_one(
    $conn,
    'SELECT userName FROM %T u JOIN %T ssh ON u.phid = ssh.userPHID
      WHERE ssh.keyBody = %s AND ssh.keyType = %s',
    $user_dao->getTableName(),
    $ssh_dao->getTableName(),
    $body,
    $type);
  if ($row) {
    $user = idx($row, 'userName');
  }
}

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
