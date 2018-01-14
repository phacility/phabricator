<?php

final class DiffusionMercurialWireClientSSHProtocolChannel
  extends PhutilProtocolChannel {

  private $buffer = '';
  private $state = 'command';
  private $expectArgumentCount;
  private $argumentName;
  private $expectBytes;
  private $command;
  private $arguments;
  private $raw;

  protected function encodeMessage($message) {
    return $message;
  }

  private function initializeState($last_command = null) {
    if ($last_command == 'unbundle') {
      $this->command = '<raw-data>';
      $this->state = 'data-length';
    } else {
      $this->state = 'command';
    }
    $this->expectArgumentCount = null;
    $this->expectBytes = null;
    $this->command = null;
    $this->argumentName = null;
    $this->arguments = array();
    $this->raw = '';
  }

  private function readProtocolLine() {
    $pos = strpos($this->buffer, "\n");

    if ($pos === false) {
      return null;
    }

    $line = substr($this->buffer, 0, $pos);

    $this->raw .= $line."\n";
    $this->buffer = substr($this->buffer, $pos + 1);

    return $line;
  }

  private function readProtocolBytes() {
    if (strlen($this->buffer) < $this->expectBytes) {
      return null;
    }

    $bytes = substr($this->buffer, 0, $this->expectBytes);
    $this->raw .= $bytes;
    $this->buffer = substr($this->buffer, $this->expectBytes);

    return $bytes;
  }

  private function newMessageAndResetState() {
    $message = array(
      'command' => $this->command,
      'arguments' => $this->arguments,
      'raw' => $this->raw,
    );
    $this->initializeState($this->command);
    return $message;
  }

  private function newDataMessage($bytes) {
    $message = array(
      'command' => '<raw-data>',
      'raw' => strlen($bytes)."\n".$bytes,
    );
    return $message;
  }

  protected function decodeStream($data) {
    $this->buffer .= $data;

    $out = array();
    $messages = array();

    while (true) {
      if ($this->state == 'command') {
        $this->initializeState();

        // We're reading a command. It looks like:
        //
        //   <command>

        $line = $this->readProtocolLine();
        if ($line === null) {
          break;
        }

        $this->command = $line;
        $this->state = 'arguments';
      } else if ($this->state == 'arguments') {

        // Check if we're still waiting for arguments.
        $args = DiffusionMercurialWireProtocol::getCommandArgs($this->command);
        $have = array_select_keys($this->arguments, $args);
        if (count($have) == count($args)) {
          // We have all the arguments. Emit a message and read the next
          // command.
          $messages[] = $this->newMessageAndResetState();
        } else {
          // We're still reading arguments. They can either look like:
          //
          //   <name> <length(value)>
          //   <value>
          //   ...
          //
          // ...or like this:
          //
          //   * <count>
          //   <name1> <length(value1)>
          //   <value1>
          //   ...

          $line = $this->readProtocolLine();
          if ($line === null) {
            break;
          }

          list($arg, $size) = explode(' ', $line, 2);
          $size = (int)$size;

          if ($arg != '*') {
            $this->expectBytes = $size;
            $this->argumentName = $arg;
            $this->state = 'value';
          } else {
            $this->arguments['*'] = array();
            $this->expectArgumentCount = $size;
            $this->state = 'argv';
          }
        }
      } else if ($this->state == 'value' || $this->state == 'argv-value') {

        // We're reading the value of an argument. We just need to wait for
        // the right number of bytes to show up.

        $bytes = $this->readProtocolBytes();
        if ($bytes === null) {
          break;
        }

        if ($this->state == 'argv-value') {
          $this->arguments['*'][$this->argumentName] = $bytes;
          $this->state = 'argv';
        } else {
          $this->arguments[$this->argumentName] = $bytes;
          $this->state = 'arguments';
        }


      } else if ($this->state == 'argv') {

        // We're reading a variable number of arguments. We need to wait for
        // the arguments to arrive.

        if ($this->expectArgumentCount) {
          $line = $this->readProtocolLine();
          if ($line === null) {
            break;
          }

          list($arg, $size) = explode(' ', $line, 2);
          $size = (int)$size;

          $this->expectBytes = $size;
          $this->argumentName = $arg;
          $this->state = 'argv-value';

          $this->expectArgumentCount--;
        } else {
          $this->state = 'arguments';
        }
      } else if ($this->state == 'data-length') {

        // We're reading the length of a chunk of raw data. It looks like
        // this:
        //
        //   <length-in-bytes>\n
        //
        // The length is human-readable text (for example, "4096"), and
        // may be 0.

        $line = $this->readProtocolLine();
        if ($line === null) {
          break;
        }
        $this->expectBytes = (int)$line;
        if (!$this->expectBytes) {
          $messages[] = $this->newDataMessage('');
          $this->initializeState();
        } else {
          $this->state = 'data-bytes';
        }
      } else if ($this->state == 'data-bytes') {

        // We're reading some known, nonzero number of raw bytes of data.

        // If we don't have any more bytes on the buffer yet, just bail:
        // otherwise, we'll emit a pointless and possibly harmful 0-byte data
        // frame. See T13036 for discussion.
        if (!strlen($this->buffer)) {
          break;
        }

        $bytes = substr($this->buffer, 0, $this->expectBytes);
        $this->buffer = substr($this->buffer, strlen($bytes));
        $this->expectBytes -= strlen($bytes);

        // NOTE: We emit a data frame as soon as we read some data. This can
        // cause us to repackage frames: for example, if we receive one large
        // frame slowly, we may emit it as several smaller frames. In theory
        // this is good; in practice, Mercurial never seems to select a frame
        // size larger than 4096 bytes naturally and this may be more
        // complexity and trouble than it is worth. See T13036.

        $messages[] = $this->newDataMessage($bytes);

        if (!$this->expectBytes) {
          // We've finished reading this chunk, so go read the next chunk.
          $this->state = 'data-length';
        } else {
          // We're waiting for more data, and have read everything available
          // to us so far.
          break;
        }
      } else {
        throw new Exception(pht("Bad parser state '%s'!", $this->state));
      }
    }

    return $messages;
  }

}
