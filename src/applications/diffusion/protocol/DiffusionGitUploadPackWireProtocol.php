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

    $capabilities = null;
    $last_frame = null;

    $refs = array();
    foreach ($frames as $key => $frame) {
      $is_last = ($key === $last_key);
      if ($is_last) {
        // This is a "0000" frame at the end of the list of refs, so we pass
        // it through unmodified after we figure out what the rest of the
        // frames should look like, below.
        $last_frame = $frame;
        continue;
      }

      $is_first = ($key === $head_key);

      // Otherwise, we expect a list of:
      //
      //   <hash> <ref-name>\0<capabilities>
      //   <hash> <ref-name>
      //   ...
      //
      // See T13309. The end of this list (which may be empty if a repository
      // does not have any refs) has a list of zero or more of these:
      //
      //   shallow <hash>
      //
      // These entries are present if the repository is a shallow clone
      // which was made with the "--depth" flag.
      //
      // Note that "shallow" frames do not advertise capabilities, and if
      // a repository has only "shallow" frames, capabilities are never
      // advertised.

      $bytes = $frame['bytes'];
      $matches = array();
      if ($is_first) {
        $capabilities_pattern = '\0(?P<capabilities>[^\n]+)';
      } else {
        $capabilities_pattern = '';
      }

      $ok = preg_match(
        '('.
          '^'.
          '(?:'.
            '(?P<hash>[0-9a-f]{40}) (?P<name>[^\0\n]+)'.$capabilities_pattern.
            '|'.
            'shallow (?P<shallow>[0-9a-f]{40})'.
          ')'.
          '\n'.
          '\z'.
        ')',
        $bytes,
        $matches);

      if (!$ok) {
        if ($is_first) {
          throw new Exception(
            pht(
              'Unexpected "git upload-pack" initial protocol frame: expected '.
              '"<hash> <name>\0<capabilities>\n", or '.
              '"shallow <hash>\n", got "%s".',
              $bytes));
        } else {
          throw new Exception(
            pht(
              'Unexpected "git upload-pack" protocol frame: expected '.
              '"<hash> <name>\n", or "shallow <hash>\n", got "%s".',
              $bytes));
        }
      }

      if (isset($matches['shallow'])) {
        $name = null;
        $hash = $matches['shallow'];
        $is_shallow = true;
      } else {
        $name = $matches['name'];
        $hash = $matches['hash'];
        $is_shallow = false;
      }

      if (isset($matches['capabilities'])) {
        $capabilities = $matches['capabilities'];
      }

      $refs[] = array(
        'hash' => $hash,
        'name' => $name,
        'shallow' => $is_shallow,
      );
    }

    $capabilities = DiffusionGitWireProtocolCapabilities::newFromWireFormat(
      $capabilities);

    $ref_list = id(new DiffusionGitWireProtocolRefList())
      ->setCapabilities($capabilities);

    foreach ($refs as $ref) {
      $wire_ref = id(new DiffusionGitWireProtocolRef())
        ->setHash($ref['hash']);

      if ($ref['shallow']) {
        $wire_ref->setIsShallow(true);
      } else {
        $wire_ref->setName($ref['name']);
      }

      $ref_list->addRef($wire_ref);
    }

    // TODO: Here, we have a structured list of refs. In a future change,
    // we are free to mutate the structure before flattening it back into
    // wire format.

    $refs = $ref_list->getRefs();

    // Before we write the ref list, sort it for consistency with native
    // Git output. We may have added, removed, or renamed refs and ended up
    // with an out-of-order list.

    $refs = msortv($refs, 'newSortVector');

    // The first ref we send back includes the capabilities data. Note that if
    // we send back no refs, we also don't send back capabilities! This is
    // a little surprising, but is consistent with the native behavior of the
    // protocol.

    // Likewise, we don't send back any capabilities if we're sending only
    // "shallow" frames.

    $output = array();
    $is_first = true;
    foreach ($refs as $ref) {
      $is_shallow = $ref->getIsShallow();

      if ($is_shallow) {
        $result = sprintf(
          "shallow %s\n",
          $ref->getHash());
      } else if ($is_first) {
        $result = sprintf(
          "%s %s\0%s\n",
          $ref->getHash(),
          $ref->getName(),
          $ref_list->getCapabilities()->toWireFormat());
      } else {
        $result = sprintf(
          "%s %s\n",
          $ref->getHash(),
          $ref->getName());
      }

      $output[] = $this->newProtocolFrame('data', $result);
      $is_first = false;
    }

    $output[] = $last_frame;

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

}
