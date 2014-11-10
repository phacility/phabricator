#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$keys = id(new PhabricatorAuthSSHKeyQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->execute();

foreach ($keys as $key => $ssh_key) {
  // For now, filter out any keys which don't belong to users. Eventually we
  // may allow devices to use this channel.
  if (!($ssh_key->getObject() instanceof PhabricatorUser)) {
    unset($keys[$key]);
    continue;
  }
}

if (!$keys) {
  echo pht('No keys found.')."\n";
  exit(1);
}

$bin = $root.'/bin/ssh-exec';
foreach ($keys as $ssh_key) {
  $user = $ssh_key->getObject()->getUsername();

  $cmd = csprintf('%s --phabricator-ssh-user %s', $bin, $user);
  // This is additional escaping for the SSH 'command="..."' string.
  $cmd = addcslashes($cmd, '"\\');

  // Strip out newlines and other nonsense from the key type and key body.

  $type = $ssh_key->getKeyType();
  $type = preg_replace('@[\x00-\x20]+@', '', $type);
  if (!strlen($type)) {
    continue;
  }

  $key = $ssh_key->getKeyBody();
  $key = preg_replace('@[\x00-\x20]+@', '', $key);
  if (!strlen($key)) {
    continue;
  }

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
