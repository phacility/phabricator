<?php

final class PhabricatorProtocolLog
  extends Phobject {

  private $logfile;
  private $mode;
  private $buffer = array();

  public function __construct($logfile) {
    $this->logfile = $logfile;
  }

  public function didStartSession($session_name) {
    $this->setMode('!');
    $this->buffer[] = $session_name;
    $this->flush();
  }

  public function didEndSession() {
    $this->setMode('_');
    $this->buffer[] = pht('<End of Session>');
    $this->flush();
  }

  public function didWriteBytes($bytes) {
    if (!strlen($bytes)) {
      return;
    }

    $this->setMode('>');
    $this->buffer[] = $bytes;
  }

  public function didReadBytes($bytes) {
    if (!strlen($bytes)) {
      return;
    }

    $this->setMode('<');
    $this->buffer[] = $bytes;
  }

  public function didReadFrame($frame) {
    $this->writeFrame('<*', $frame);
  }

  public function didWriteFrame($frame) {
    $this->writeFrame('>*', $frame);
  }

  private function writeFrame($header, $frame) {
    $this->flush();

    $frame = explode("\n", $frame);
    foreach ($frame as $key => $line) {
      $frame[$key] = $header.'  '.$this->escapeBytes($line);
    }
    $frame = implode("\n", $frame)."\n\n";

    $this->writeMessage($frame);
  }

  private function setMode($mode) {
    if ($this->mode === $mode) {
      return $this;
    }

    if ($this->mode !== null) {
      $this->flush();
    }

    $this->mode = $mode;

    return $this;
  }

  private function flush() {
    $mode = $this->mode;
    $bytes = $this->buffer;

    $this->mode = null;
    $this->buffer = array();

    $bytes = implode('', $bytes);

    if (strlen($bytes)) {
      $this->writeBytes($mode, $bytes);
    }
  }

  private function writeBytes($mode, $bytes) {
    $header = $mode;
    $len = strlen($bytes);

    $out = array();
    switch ($mode) {
      case '<':
        $out[] = pht('%s Write [%s bytes]', $header, new PhutilNumber($len));
        break;
      case '>':
        $out[] = pht('%s Read [%s bytes]', $header, new PhutilNumber($len));
        break;
      default:
        $out[] = pht(
          '%s %s',
          $header,
          $this->escapeBytes($bytes));
        break;
    }

    switch ($mode) {
      case '<':
      case '>':
        $out[] = $this->renderBytes($header, $bytes);
        break;
    }

    $out = implode("\n", $out)."\n\n";

    $this->writeMessage($out);
  }

  private function renderBytes($header, $bytes) {
    $bytes_per_line = 48;
    $bytes_per_chunk = 4;

    // Compute the width of the "bytes" display section, which looks like
    // this:
    //
    // >  00112233 44556677  abcdefgh
    //    ^^^^^^^^^^^^^^^^^
    //
    // We need to figure this out so we can align the plain text in the far
    // right column appropriately.

    // The character width of the "bytes" part of a full display line. If
    // we're rendering 48 bytes per line, we'll need 96 characters, since
    // each byte is printed as a 2-character hexadecimal code.
    $display_bytes = ($bytes_per_line * 2);

    // The character width of the number of spaces in between the "bytes"
    // chunks. If we're rendering 12 chunks per line, we'll put 11 spaces
    // in between them to separate them.
    $display_spaces = (($bytes_per_line / $bytes_per_chunk) - 1);

    $pad_bytes = $display_bytes + $display_spaces;

    // When the protocol is plaintext, try to break it on newlines so it's
    // easier to read.
    $pos = 0;
    $lines = array();
    while (true) {
      $next_break = strpos($bytes, "\n", $pos);
      if ($next_break === false) {
        $len = strlen($bytes) - $pos;
      } else {
        $len = ($next_break - $pos) + 1;
      }
      $len = min($bytes_per_line, $len);

      $next_bytes = substr($bytes, $pos, $len);

      $chunk_parts = array();
      foreach (str_split($next_bytes, $bytes_per_chunk) as $chunk) {
        $chunk_display = '';
        for ($ii = 0; $ii < strlen($chunk); $ii++) {
          $chunk_display .= sprintf('%02x', ord($chunk[$ii]));
        }
        $chunk_parts[] = $chunk_display;
      }
      $chunk_parts = implode(' ', $chunk_parts);

      $chunk_parts = str_pad($chunk_parts, $pad_bytes, ' ');


      $lines[] = $header.'  '.$chunk_parts.'  '.$this->escapeBytes($next_bytes);

      $pos += $len;

      if ($pos >= strlen($bytes)) {
        break;
      }
    }

    $lines = implode("\n", $lines);

    return $lines;
  }

  private function escapeBytes($bytes) {
    $result = '';
    for ($ii = 0; $ii < strlen($bytes); $ii++) {
      $c = $bytes[$ii];
      $o = ord($c);

      if ($o >= 0x20 && $o <= 0x7F) {
        $result .= $c;
      } else {
        $result .= '.';
      }
    }
    return $result;
  }

  private function writeMessage($message) {
    $f = fopen($this->logfile, 'a');
    fwrite($f, $message);
    fflush($f);
    fclose($f);
  }

}
