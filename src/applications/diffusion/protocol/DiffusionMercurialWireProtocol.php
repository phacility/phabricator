<?php

final class DiffusionMercurialWireProtocol {

  public static function getCommandArgs($command) {
    // We need to enumerate all of the Mercurial wire commands because the
    // argument encoding varies based on the command. "Why?", you might ask,
    // "Why would you do this?".

    $commands = array(
      'batch' => array('cmds', '*'),
      'between' => array('pairs'),
      'branchmap' => array(),
      'branches' => array('nodes'),
      'capabilities' => array(),
      'changegroup' => array('roots'),
      'changegroupsubset' => array('bases heads'),
      'debugwireargs' => array('one two *'),
      'getbundle' => array('*'),
      'heads' => array(),
      'hello' => array(),
      'known' => array('nodes', '*'),
      'listkeys' => array('namespace'),
      'lookup' => array('key'),
      'pushkey' => array('namespace', 'key', 'old', 'new'),
      'stream_out' => array(''),
      'unbundle' => array('heads'),
    );

    if (!isset($commands[$command])) {
      throw new Exception("Unknown Mercurial command '{$command}!");
    }

    return $commands[$command];
  }

  public static function isReadOnlyCommand($command) {
    $read_only = array(
      'between' => true,
      'branchmap' => true,
      'branches' => true,
      'capabilities' => true,
      'changegroup' => true,
      'changegroupsubset' => true,
      'debugwireargs' => true,
      'getbundle' => true,
      'heads' => true,
      'hello' => true,
      'known' => true,
      'listkeys' => true,
      'lookup' => true,
      'stream_out' => true,
    );

    // Notably, the write commands are "pushkey" and "unbundle". The
    // "batch" command is theoretically read only, but we require explicit
    // analysis of the actual commands.

    return isset($read_only[$command]);
  }

}
