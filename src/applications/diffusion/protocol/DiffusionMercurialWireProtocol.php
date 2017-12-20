<?php

final class DiffusionMercurialWireProtocol extends Phobject {

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
      throw new Exception(pht("Unknown Mercurial command '%s!", $command));
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

  public static function isReadOnlyBatchCommand($cmds) {
    if (!strlen($cmds)) {
      // We expect a "batch" command to always have a "cmds" string, so err
      // on the side of caution and throw if we don't get any data here. This
      // either indicates a mangled command from the client or a programming
      // error in our code.
      throw new Exception(pht("Expected nonempty '%s' specification!", 'cmds'));
    }

    // For "batch" we get a "cmds" argument like:
    //
    //   heads ;known nodes=
    //
    // We need to examine the commands (here, "heads" and "known") to make sure
    // they're all read-only.

    // NOTE: Mercurial has some code to escape semicolons, but it does not
    // actually function for command separation. For example, these two batch
    // commands will produce completely different results (the former will run
    // the lookup; the latter will fail with a parser error):
    //
    //  lookup key=a:xb;lookup key=z* 0
    //  lookup key=a:;b;lookup key=z* 0
    //               ^
    //               |
    //               +-- Note semicolon.
    //
    // So just split unconditionally.

    $cmds = explode(';', $cmds);
    foreach ($cmds as $sub_cmd) {
      $name = head(explode(' ', $sub_cmd, 2));
      if (!self::isReadOnlyCommand($name)) {
        return false;
      }
    }

    return true;
  }

  /** If the server version is running 3.4+ it will respond
   * with 'bundle2' capability in the format of "bundle2=(url-encoding)".
   * Until we manage to properly package up bundles to send back we
   * disallow the client from knowing we speak bundle2 by removing it
   * from the capabilities listing.
   *
   * The format of the capabilities string is: "a space separated list
   * of strings representing what commands the server supports"
   * @link https://www.mercurial-scm.org/wiki/CommandServer#Protocol
   *
   * @param string  $capabilities - The string of capabilities to
   *   strip the bundle2 capability from. This is expected to be
   *   the space-separated list of strings resulting from the
   *   querying the 'capabilities' command.
   *
   * @return string  The resulting space-separated list of capabilities
   *   which no longer contains the 'bundle2' capability. This is meant
   *   to replace the original $body to send back to client.
   */
  public static function filterBundle2Capability($capabilities) {
    $parts = explode(' ', $capabilities);
    foreach ($parts as $key => $part) {
      if (preg_match('/^bundle2=/', $part)) {
        unset($parts[$key]);
        break;
      }
    }
    return implode(' ', $parts);
  }

}
