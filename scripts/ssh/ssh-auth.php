#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$cache = PhabricatorCaches::getMutableCache();
$authfile_key = PhabricatorAuthSSHKeyQuery::AUTHFILE_CACHEKEY;
$authfile = $cache->getKey($authfile_key);

if ($authfile === null) {
  $keys = id(new PhabricatorAuthSSHKeyQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withIsActive(true)
    ->execute();

  if (!$keys) {
    echo pht('No keys found.')."\n";
    exit(1);
  }

  $bin = $root.'/bin/ssh-exec';
  foreach ($keys as $ssh_key) {
    $key_argv = array();
    $object = $ssh_key->getObject();
    if ($object instanceof PhabricatorUser) {
      $key_argv[] = '--phabricator-ssh-user';
      $key_argv[] = $object->getUsername();
    } else if ($object instanceof AlmanacDevice) {
      if (!$ssh_key->getIsTrusted()) {
        // If this key is not a trusted device key, don't allow SSH
        // authentication.
        continue;
      }
      $key_argv[] = '--phabricator-ssh-device';
      $key_argv[] = $object->getName();
    } else {
      // We don't know what sort of key this is; don't permit SSH auth.
      continue;
    }

    $key_argv[] = '--phabricator-ssh-key';
    $key_argv[] = $ssh_key->getID();

    $cmd = csprintf('%s %Ls', $bin, $key_argv);

    $instance = PhabricatorEnv::getEnvConfig('cluster.instance');
    if (strlen($instance)) {
      $cmd = csprintf('PHABRICATOR_INSTANCE=%s %C', $instance, $cmd);
    }

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

  $authfile = implode('', $lines);
  $ttl = phutil_units('24 hours in seconds');
  $cache->setKey($authfile_key, $authfile, $ttl);
}

echo $authfile;
exit(0);
