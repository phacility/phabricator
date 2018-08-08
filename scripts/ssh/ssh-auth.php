#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-script.php';

// NOTE: We are caching a datastructure rather than the flat key file because
// the path on disk to "ssh-exec" is arbitrarily mutable at runtime. See T12397.

$cache = PhabricatorCaches::getMutableCache();
$authstruct_key = PhabricatorAuthSSHKeyQuery::AUTHSTRUCT_CACHEKEY;
$authstruct_raw = $cache->getKey($authstruct_key);

$authstruct = null;

if (strlen($authstruct_raw)) {
  try {
    $authstruct = phutil_json_decode($authstruct_raw);
  } catch (Exception $ex) {
    // Ignore any issues with the cached data; we'll just rebuild the
    // structure below.
  }
}

if ($authstruct === null) {
  $keys = id(new PhabricatorAuthSSHKeyQuery())
    ->setViewer(PhabricatorUser::getOmnipotentUser())
    ->withIsActive(true)
    ->execute();

  if (!$keys) {
    echo pht('No keys found.')."\n";
    exit(1);
  }

  $key_list = array();
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

    $key_list[] = array(
      'argv' => $key_argv,
      'type' => $type,
      'key' => $key,
    );
  }

  $authstruct = array(
    'keys' => $key_list,
  );

  $authstruct_raw = phutil_json_encode($authstruct);
  $ttl = phutil_units('24 hours in seconds');
  $cache->setKey($authstruct_key, $authstruct_raw, $ttl);
}

$bin = $root.'/bin/ssh-exec';
$instance = PhabricatorEnv::getEnvConfig('cluster.instance');

$lines = array();
foreach ($authstruct['keys'] as $key_struct) {
  $key_argv = $key_struct['argv'];
  $key = $key_struct['key'];
  $type = $key_struct['type'];

  $cmd = csprintf('%s %Ls', $bin, $key_argv);

  if (strlen($instance)) {
    $cmd = csprintf('PHABRICATOR_INSTANCE=%s %C', $instance, $cmd);
  }

  // This is additional escaping for the SSH 'command="..."' string.
  $cmd = addcslashes($cmd, '"\\');

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

echo $authfile;

exit(0);
