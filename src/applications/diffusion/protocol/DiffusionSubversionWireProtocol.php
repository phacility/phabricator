<?php

final class DiffusionSubversionWireProtocol extends Phobject {

  private $buffer = '';
  private $state = 'item';
  private $expectBytes = 0;
  private $byteBuffer = '';
  private $stack = array();
  private $list = array();
  private $raw = '';

  private function pushList() {
    $this->stack[] = $this->list;
    $this->list = array();
  }

  private function popList() {
    $list = $this->list;
    $this->list = array_pop($this->stack);
    return $list;
  }

  private function pushItem($item, $type) {
    $this->list[] = array(
      'type' => $type,
      'value' => $item,
    );
  }

  public function writeData($data) {
    $this->buffer .= $data;

    $messages = array();
    while (true) {
      if ($this->state == 'space') {
        // Consume zero or more extra spaces after matching an item. The
        // protocol requires at least one space, but allows more than one.

        $matches = null;
        if (!preg_match('/^(\s*)\S/', $this->buffer, $matches)) {
         // Wait for more data.
          break;
        }

        // We have zero or more spaces and then some other character, so throw
        // the spaces away and continue parsing frames.
        if (strlen($matches[1])) {
          $this->buffer = substr($this->buffer, strlen($matches[1]));
        }

        $this->state = 'item';
      } else if ($this->state == 'item') {
        $match = null;
        $result = null;
        $buf = $this->buffer;
        if (preg_match('/^([a-z][a-z0-9-]*)\s/i', $buf, $match)) {
          $this->pushItem($match[1], 'word');
        } else if (preg_match('/^(\d+)\s/', $buf, $match)) {
          $this->pushItem((int)$match[1], 'number');
        } else if (preg_match('/^(\d+):/', $buf, $match)) {
          // NOTE: The "+ 1" includes the space after the string.
          $this->expectBytes = (int)$match[1] + 1;
          $this->state = 'bytes';
        } else if (preg_match('/^(\\()\s/', $buf, $match)) {
          $this->pushList();
        } else if (preg_match('/^(\\))\s/', $buf, $match)) {
          $list = $this->popList();
          if ($this->stack) {
            $this->pushItem($list, 'list');
          } else {
            $result = $list;
          }
        } else {
          $match = false;
        }

        if ($match !== false) {
          $this->raw .= substr($this->buffer, 0, strlen($match[0]));
          $this->buffer = substr($this->buffer, strlen($match[0]));

          if ($result !== null) {
            $messages[] = array(
              'structure' => $list,
              'raw' => $this->raw,
            );
            $this->raw = '';
          }

          // Consume any extra whitespace after an item. If we're in the
          // "bytes" state, we aren't looking for whitespace.
          if ($this->state == 'item') {
            $this->state = 'space';
          }
        } else {
          // No matches yet, wait for more data.
          break;
        }
      } else if ($this->state == 'bytes') {
        $new_data = substr($this->buffer, 0, $this->expectBytes);
        if (!strlen($new_data)) {
          // No more bytes available yet, wait for more data.
          break;
        }
        $this->buffer = substr($this->buffer, strlen($new_data));

        $this->expectBytes -= strlen($new_data);
        $this->raw .= $new_data;
        $this->byteBuffer .= $new_data;

        if (!$this->expectBytes) {
          $this->state = 'byte-space';
          // Strip off the terminal space.
          $this->pushItem(substr($this->byteBuffer, 0, -1), 'string');
          $this->byteBuffer = '';
          $this->state = 'space';
        }
      } else {
        throw new Exception(pht("Invalid state '%s'!", $this->state));
      }
    }

    return $messages;
  }

  /**
   * Convert a parsed command struct into a wire protocol string.
   */
  public function serializeStruct(array $struct) {
    $out = array();

    $out[] = '( ';
    foreach ($struct as $item) {
      $value = $item['value'];
      $type = $item['type'];
      switch ($type) {
        case 'word':
          $out[] = $value;
          break;
        case 'number':
          $out[] = $value;
          break;
        case 'string':
          $out[] = strlen($value).':'.$value;
          break;
        case 'list':
          $out[] = self::serializeStruct($value);
          break;
        default:
          throw new Exception(
            pht(
              "Unknown SVN wire protocol structure '%s'!",
              $type));
      }
      if ($type != 'list') {
        $out[] = ' ';
      }
    }
    $out[] = ') ';

    return implode('', $out);
  }

  public function isReadOnlyCommand(array $struct) {
    if (empty($struct[0]['type']) || ($struct[0]['type'] != 'word')) {
      // This isn't what we expect; fail defensively.
      throw new Exception(
        pht(
          "Unexpected command structure, expected '%s'.",
          '( word ... )'));
    }

    switch ($struct[0]['value']) {
      // Authentication command set.
      case 'EXTERNAL':

      // The "Main" command set. Some of the commands in this command set are
      // mutation commands, and are omitted from this list.
      case 'reparent':
      case 'get-latest-rev':
      case 'get-dated-rev':
      case 'rev-proplist':
      case 'rev-prop':
      case 'get-file':
      case 'get-dir':
      case 'check-path':
      case 'stat':
      case 'update':
      case 'get-mergeinfo':
      case 'switch':
      case 'status':
      case 'diff':
      case 'log':
      case 'get-file-revs':
      case 'get-locations':

      // The "Report" command set. These are not actually mutation
      // operations, they just define a request for information.
      case 'set-path':
      case 'delete-path':
      case 'link-path':
      case 'finish-report':
      case 'abort-report':

      // These are used to report command results.
      case 'success':
      case 'failure':

        // If we get here, we've matched some known read-only command.
        return true;
      default:
        // Anything else isn't a known read-only command, so require write
        // access to use it.
        break;
    }

    return false;
  }

}
