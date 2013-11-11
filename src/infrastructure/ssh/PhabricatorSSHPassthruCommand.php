<?php

/**
 * Proxy an IO channel to an underlying command, with optional callbacks. This
 * is a mostly a more general version of @{class:PhutilExecPassthru}. This
 * class is used to proxy Git, SVN and Mercurial traffic to the commands which
 * can actually serve it.
 *
 * Largely, this just reads an IO channel (like stdin from SSH) and writes
 * the results into a command channel (like a command's stdin). Then it reads
 * the command channel (like the command's stdout) and writes it into the IO
 * channel (like stdout from SSH):
 *
 *    IO Channel        Command Channel
 *    stdin       ->    stdin
 *    stdout      <-    stdout
 *    stderr      <-    stderr
 *
 * You can provide **read and write callbacks** which are invoked as data
 * is passed through this class. They allow you to inspect and modify traffic.
 *
 *    IO Channel     Passthru        Command Channel
 *    stdout     ->  willWrite   ->  stdin
 *    stdin      <-  willRead    <-  stdout
 *    stderr     <-  (identity)  <-  stderr
 *
 * Primarily, this means:
 *
 *   - the **IO Channel** can be a @{class:PhutilProtocolChannel} if the
 *     **write callback** can convert protocol messages into strings; and
 *   - the **write callback** can inspect and reject requests over the channel,
 *     e.g. to enforce policies.
 *
 * In practice, this is used when serving repositories to check each command
 * issued over SSH and determine if it is a read command or a write command.
 * Writes can then be checked for appropriate permissions.
 */
final class PhabricatorSSHPassthruCommand extends Phobject {

  private $commandChannel;
  private $ioChannel;
  private $errorChannel;
  private $execFuture;
  private $willWriteCallback;
  private $willReadCallback;

  public function setCommandChannelFromExecFuture(ExecFuture $exec_future) {
    $exec_channel = new PhutilExecChannel($exec_future);
    $exec_channel->setStderrHandler(array($this, 'writeErrorIOCallback'));

    $this->execFuture = $exec_future;
    $this->commandChannel = $exec_channel;

    return $this;
  }

  public function setIOChannel(PhutilChannel $io_channel) {
    $this->ioChannel = $io_channel;
    return $this;
  }

  public function setErrorChannel(PhutilChannel $error_channel) {
    $this->errorChannel = $error_channel;
    return $this;
  }

  public function setWillReadCallback($will_read_callback) {
    $this->willReadCallback = $will_read_callback;
    return $this;
  }

  public function setWillWriteCallback($will_write_callback) {
    $this->willWriteCallback = $will_write_callback;
    return $this;
  }

  public function writeErrorIOCallback(PhutilChannel $channel, $data) {
    $this->errorChannel->write($data);

    // TODO: Because of the way `waitForAny()` works, we degrade to a busy
    // wait if we hand it a writable, write-only channel. We should handle this
    // case better in `waitForAny()`. For now, just flush the error channel
    // explicity after writing data over it.

    $this->errorChannel->flush();
  }

  public function execute() {
    $command_channel = $this->commandChannel;
    $io_channel = $this->ioChannel;
    $error_channel = $this->errorChannel;

    if (!$command_channel) {
      throw new Exception("Set a command channel before calling execute()!");
    }

    if (!$io_channel) {
      throw new Exception("Set an IO channel before calling execute()!");
    }

    if (!$error_channel) {
      throw new Exception("Set an error channel before calling execute()!");
    }

    $channels = array($command_channel, $io_channel, $error_channel);

    while (true) {
      // TODO: See note in writeErrorIOCallback!
      $wait = array($command_channel, $io_channel);
      PhutilChannel::waitForAny($wait);

      $io_channel->update();
      $command_channel->update();
      $error_channel->update();

      $done = !$command_channel->isOpen();

      $in_message = $io_channel->read();
      if ($in_message !== null) {
        $in_message = $this->willWriteData($in_message);
        if ($in_message !== null) {
          $command_channel->write($in_message);
        }
      }

      $out_message = $command_channel->read();
      if ($out_message !== null) {
        $out_message = $this->willReadData($out_message);
        if ($out_message !== null) {
          $io_channel->write($out_message);
        }
      }

      // If we have nothing left on stdin, close stdin on the subprocess.
      if (!$io_channel->isOpenForReading()) {
        $command_channel->closeWriteChannel();
      }

      if ($done) {
        break;
      }

      // If the client has disconnected, kill the subprocess and bail.
      if (!$io_channel->isOpenForWriting()) {
        $this->execFuture->resolveKill();
        break;
      }
    }

    list($err) = $this->execFuture->resolve();

    return $err;
  }

  public function willWriteData($message) {
    if ($this->willWriteCallback) {
      return call_user_func($this->willWriteCallback, $this, $message);
    } else {
      if (strlen($message)) {
        return $message;
      } else {
        return null;
      }
    }
  }

  public function willReadData($message) {
    if ($this->willReadCallback) {
      return call_user_func($this->willReadCallback, $this, $message);
    } else {
      if (strlen($message)) {
        return $message;
      } else {
        return null;
      }
    }
  }

}
