<?php

// TODO: See T13321. After the removal of daemon PID files this class
// no longer makes as much sense as it once did.

final class PhabricatorDaemonReference extends Phobject {

  public static function isProcessRunning($pid) {
    if (!$pid) {
      return false;
    }

    if (function_exists('posix_kill')) {
      // This may fail if we can't signal the process because we are running as
      // a different user (for example, we are 'apache' and the process is some
      // other user's, or we are a normal user and the process is root's), but
      // we can check the error code to figure out if the process exists.
      $is_running = posix_kill($pid, 0);
      if (posix_get_last_error() == 1) {
        // "Operation Not Permitted", indicates that the PID exists. If it
        // doesn't, we'll get an error 3 ("No such process") instead.
        $is_running = true;
      }
    } else {
      // If we don't have the posix extension, just exec.
      list($err) = exec_manual('ps %s', $pid);
      $is_running = ($err == 0);
    }

    return $is_running;
  }

}
