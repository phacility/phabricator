#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$user_dao = new PhabricatorUser();
$ssh_dao = new PhabricatorUserSSHKey();
$conn_r = $user_dao->establishConnection('r');

$rows = queryfx_all(
  $conn_r,
  'SELECT userName, keyBody, keyType FROM %T u JOIN %T ssh
    ON u.phid = ssh.userPHID',
  $user_dao->getTableName(),
  $ssh_dao->getTableName());

$bin = $root.'/bin/ssh-exec';
foreach ($rows as $row) {
  $user = $row['userName'];

  $cmd = csprintf('%s --phabricator-ssh-user %s', $bin, $user);
  // This is additional escaping for the SSH 'command="..."' string.
  $cmd = addcslashes($cmd, '"\\');

  // Strip out newlines and other nonsense from the key type and key body.

  $type = $row['keyType'];
  $type = preg_replace('@[\x00-\x20]+@', '', $type);

  $key = $row['keyBody'];
  $key = preg_replace('@[\x00-\x20]+@', '', $key);


  $options = array(
    'command="'.$cmd.'"',
    'no-port-forwarding',
    'no-X11-forwarding',
    'no-agent-forwarding',
    'no-pty',
  );
  $options = implode(',', $options);

  $lines[] = $options.' '.$type.' '.$key."\n";
}

echo implode('', $lines);
exit(0);
