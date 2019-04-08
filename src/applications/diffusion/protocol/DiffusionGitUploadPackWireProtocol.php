<?php

final class DiffusionGitUploadPackWireProtocol
  extends DiffusionGitWireProtocol {

  private $readMode = 'length';
  private $readBuffer;
  private $readFrameLength;
  private $readFrames = array();

  private $readFrameMode = 'refs';
  private $refFrames = array();

  private $readMessages = array();

  public function willReadBytes($bytes) {
    if ($this->readBuffer === null) {
      $this->readBuffer = new PhutilRope();
    }
    $buffer = $this->readBuffer;

    $buffer->append($bytes);

    while (true) {
      $len = $buffer->getByteLength();
      switch ($this->readMode) {
        case 'length':
          // We're expecting 4 bytes containing the length of the protocol
          // frame as hexadecimal in ASCII text, like "01ab". Wait until we
          // see at least 4 bytes on the wire.
          if ($len < 4) {
            if ($len > 0) {
              $bytes = $this->peekBytes($len);
              if (!preg_match('/^[0-9a-f]+\z/', $bytes)) {
                throw new Exception(
                  pht(
                    'Bad frame length character in Git protocol ("%s"), '.
                    'expected a 4-digit hexadecimal value encoded as ASCII '.
                    'text.',
                    $bytes));
              }
            }

            // We can't make any more progress until we get enough bytes, so
            // we're done with state processing.
            break 2;
          }

          $frame_length = $this->readBytes(4);
          $frame_length = hexdec($frame_length);

          // Note that the frame length includes the 4 header bytes, so we
          // usually expect a length of 5 or larger. Frames with length 0
          // are boundaries.
          if ($frame_length === 0) {
            $this->readFrames[] = $this->newProtocolFrame('null', '');
          } else if ($frame_length >= 1 && $frame_length <= 3) {
            throw new Exception(
              pht(
                'Encountered Git protocol frame with unexpected frame '.
                'length (%s)!',
                $frame_length));
          } else {
            $this->readFrameLength = $frame_length - 4;
            $this->readMode = 'frame';
          }

          break;
        case 'frame':
          // We're expecting a protocol frame of a specified length. Note that
          // it is possible for a frame to have length 0.

          // We don't have enough bytes yet, so wait for more.
          if ($len < $this->readFrameLength) {
            break 2;
          }

          if ($this->readFrameLength > 0) {
            $bytes = $this->readBytes($this->readFrameLength);
          } else {
            $bytes = '';
          }

          // Emit a protocol frame.
          $this->readFrames[] = $this->newProtocolFrame('data', $bytes);
          $this->readMode = 'length';
          break;
      }
    }

    while (true) {
      switch ($this->readFrameMode) {
        case 'refs':
          if (!$this->readFrames) {
            break 2;
          }

          foreach ($this->readFrames as $key => $frame) {
            unset($this->readFrames[$key]);

            if ($frame['type'] === 'null') {
              $ref_frames = $this->refFrames;
              $this->refFrames = array();

              $ref_frames[] = $frame;

              $this->readMessages[] = $this->newProtocolRefMessage($ref_frames);
              $this->readFrameMode = 'passthru';
              break;
            } else {
              $this->refFrames[] = $frame;
            }
          }

          break;
        case 'passthru':
          if (!$this->readFrames) {
            break 2;
          }

          $this->readMessages[] = $this->newProtocolDataMessage(
            $this->readFrames);
          $this->readFrames = array();

          break;
      }
    }

    $wire = array();
    foreach ($this->readMessages as $key => $message) {
      $wire[] = $message;
      unset($this->readMessages[$key]);
    }
    $wire = implode('', $wire);

    return $wire;
  }

  public function willWriteBytes($bytes) {
    return $bytes;
  }

  private function readBytes($count) {
    $buffer = $this->readBuffer;

    $bytes = $buffer->getPrefixBytes($count);
    $buffer->removeBytesFromHead($count);

    return $bytes;
  }

  private function peekBytes($count) {
    $buffer = $this->readBuffer;
    return $buffer->getPrefixBytes($count);
  }

  private function newProtocolFrame($type, $bytes) {
    return array(
      'type' => $type,
      'length' => strlen($bytes),
      'bytes' => $bytes,
    );
  }

  private function newProtocolRefMessage(array $frames) {
    $head_key = head_key($frames);
    $last_key = last_key($frames);

    $output = array();
    foreach ($frames as $key => $frame) {
      $is_last = ($key === $last_key);
      if ($is_last) {
        $output[] = $frame;
        // This is a "0000" frame at the end of the list of refs, so we pass
        // it through unmodified.
        continue;
      }

      $is_first = ($key === $head_key);

      // Otherwise, we expect a list of:
      //
      //   <hash> <ref-name>\0<capabilities>
      //   <hash> <ref-name>
      //   ...

      $bytes = $frame['bytes'];
      $matches = array();
      if ($is_first) {
        $ok = preg_match(
          '('.
            '^'.
            '(?P<hash>[0-9a-f]{40})'.
            ' '.
            '(?P<name>[^\0\n]+)'.
            '\0'.
            '(?P<capabilities>[^\n]+)'.
            '\n'.
            '\z'.
          ')',
          $bytes,
          $matches);
        if (!$ok) {
          throw new Exception(
            pht(
              'Unexpected "git upload-pack" initial protocol frame: expected '.
              '"<hash> <name>\0<capabilities>\n", got "%s".',
              $bytes));
        }
      } else {
        $ok = preg_match(
          '('.
            '^'.
            '(?P<hash>[0-9a-f]{40})'.
            ' '.
            '(?P<name>[^\0\n]+)'.
            '\n'.
            '\z'.
          ')',
          $bytes,
          $matches);
        if (!$ok) {
          throw new Exception(
            pht(
              'Unexpected "git upload-pack" protocol frame: expected '.
              '"<hash> <name>\n", got "%s".',
              $bytes));
        }
      }

      $hash = $matches['hash'];
      $name = $matches['name'];
      $capabilities = idx($matches, 'capabilities');

      $ref = array(
        'hash' => $hash,
        'name' => $name,
        'capabilities' => $capabilities,
      );

      $old_ref = $ref;

      $ref = $this->willReadRef($ref);

      $new_ref = $ref;

      $this->didRewriteRef($old_ref, $new_ref);

      if ($ref === null) {
        continue;
      }

      if (isset($ref['capabilities'])) {
        $result = sprintf(
          "%s %s\0%s\n",
          $ref['hash'],
          $ref['name'],
          $ref['capabilities']);
      } else {
        $result = sprintf(
          "%s %s\n",
          $ref['hash'],
          $ref['name']);
      }

      $output[] = $this->newProtocolFrame('data', $result);
    }

    return $this->newProtocolDataMessage($output);
  }

  private function newProtocolDataMessage(array $frames) {
    $message = array();

    foreach ($frames as $frame) {
      switch ($frame['type']) {
        case 'null':
          $message[] = '0000';
          break;
        case 'data':
          $message[] = sprintf(
            '%04x%s',
            $frame['length'] + 4,
            $frame['bytes']);
          break;
      }
    }

    $message = implode('', $message);

    return $message;
  }

  private function willReadRef(array $ref) {
    return $ref;
  }

  private function didRewriteRef($old_ref, $new_ref) {
    $log = $this->getProtocolLog();
    if (!$log) {
      return;
    }

    if (!$old_ref) {
      $old_name = null;
    } else {
      $old_name = $old_ref['name'];
    }

    if (!$new_ref) {
      $new_name = null;
    } else {
      $new_name = $new_ref['name'];
    }

    if ($old_name === $new_name) {
      return;
    }

    if ($old_name === null) {
      $old_name = '<null>';
    }

    if ($new_name === null) {
      $new_name = '<null>';
    }

    $log->didWriteFrame(
      pht(
        'Rewrite Ref: %s -> %s',
        $old_name,
        $new_name));
  }

}
