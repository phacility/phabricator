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
  private $pauseIOReads;

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
  }

  public function setPauseIOReads($pause) {
    $this->pauseIOReads = $pause;
    return $this;
  }

  public function execute() {
    $command_channel = $this->commandChannel;
    $io_channel = $this->ioChannel;
    $error_channel = $this->errorChannel;

    if (!$command_channel) {
      throw new Exception(
        pht(
          'Set a command channel before calling %s!',
          __FUNCTION__.'()'));
    }

    if (!$io_channel) {
      throw new Exception(
        pht(
          'Set an IO channel before calling %s!',
          __FUNCTION__.'()'));
    }

    if (!$error_channel) {
      throw new Exception(
        pht(
          'Set an error channel before calling %s!',
          __FUNCTION__.'()'));
    }

    $channels = array($command_channel, $io_channel, $error_channel);

    // We want to limit the amount of data we'll hold in memory for this
    // process. See T4241 for a discussion of this issue in general.

    $buffer_size = (1024 * 1024); // 1MB
    $io_channel->setReadBufferSize($buffer_size);
    $command_channel->setReadBufferSize($buffer_size);

    // TODO: This just makes us throw away stderr after the first 1MB, but we
    // don't currently have the support infrastructure to buffer it correctly.
    // It's difficult to imagine this causing problems in practice, though.
    $this->execFuture->getStderrSizeLimit($buffer_size);

    while (true) {
      PhutilChannel::waitForAny($channels);

      $io_channel->update();
      $command_channel->update();
      $error_channel->update();

      // If any channel is blocked on the other end, wait for it to flush before
      // we continue reading. For example, if a user is running `git clone` on
      // a 1GB repository, the underlying `git-upload-pack` may
      // be able to produce data much more quickly than we can send it over
      // the network. If we don't throttle the reads, we may only send a few
      // MB over the I/O channel in the time it takes to read the entire 1GB off
      // the command channel. That leaves us with 1GB of data in memory.

      while ($command_channel->isOpen() &&
             $io_channel->isOpenForWriting() &&
             ($command_channel->getWriteBufferSize() >= $buffer_size ||
             $io_channel->getWriteBufferSize() >= $buffer_size ||
             $error_channel->getWriteBufferSize() >= $buffer_size)) {
        PhutilChannel::waitForActivity(array(), $channels);
        $io_channel->update();
        $command_channel->update();
        $error_channel->update();
      }

      // If the subprocess has exited and we've read everything from it,
      // we're all done.
      $done = !$command_channel->isOpenForReading() &&
               $command_channel->isReadBufferEmpty();

      if (!$this->pauseIOReads) {
        $in_message = $io_channel->read();
        if ($in_message !== null) {
          $this->writeIORead($in_message);
        }
      }

      $out_message = $command_channel->read();
      if (strlen($out_message)) {
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
        $this->execFuture
          ->setStdoutSizeLimit(0)
          ->setStderrSizeLimit(0)
          ->setReadBufferSize(null)
          ->resolveKill();
        break;
      }
    }

    list($err) = $this->execFuture
      ->setStdoutSizeLimit(0)
      ->setStderrSizeLimit(0)
      ->setReadBufferSize(null)
      ->resolve();

    return $err;
  }

  public function writeIORead($in_message) {
    $in_message = $this->willWriteData($in_message);
    if (strlen($in_message)) {
      $this->commandChannel->write($in_message);
    }
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
