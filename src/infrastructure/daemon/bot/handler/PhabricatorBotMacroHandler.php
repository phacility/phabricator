<?php

final class PhabricatorBotMacroHandler extends PhabricatorBotHandler {

  private $macros;
  private $regexp;

  private $next = 0;

  private function init() {
    if ($this->macros === false) {
      return false;
    }

    if ($this->macros !== null) {
      return true;
    }

    $macros = $this->getConduit()->callMethodSynchronous(
      'macro.query',
      array());

    // If we have no macros, cache `false` (meaning "no macros") and return
    // immediately.
    if (!$macros) {
      $this->macros = false;
      return false;
    }

    $regexp = array();
    foreach ($macros as $macro_name => $macro) {
      $regexp[] = preg_quote($macro_name, '/');
    }
    $regexp = '/^('.implode('|', $regexp).')\z/';

    $this->macros = $macros;
    $this->regexp = $regexp;

    return true;
  }

  public function receiveMessage(PhabricatorBotMessage $message) {
    if (!$this->init()) {
      return;
    }

    switch ($message->getCommand()) {
      case 'MESSAGE':
        $message_body = $message->getBody();

        $matches = null;
        if (!preg_match($this->regexp, trim($message_body), $matches)) {
          return;
        }

        $macro = $matches[1];

        $ascii = idx($this->macros[$macro], 'ascii');
        if ($ascii === false) {
          return;
        }

        if (!$ascii) {
          $this->macros[$macro]['ascii'] = $this->rasterize(
            $this->macros[$macro],
            $this->getConfig('macro.size', 48),
            $this->getConfig('macro.aspect', 0.66));
          $ascii = $this->macros[$macro]['ascii'];
        }

        if ($ascii === false) {
          // If we failed to rasterize the macro, bail out.
          return;
        }

        $target_name = $message->getTarget()->getName();
        foreach ($ascii as $line) {
          $this->replyTo($message, $line);
        }
        break;
    }
  }

  public function rasterize($macro, $size, $aspect) {
    try {
      $image = $this->getConduit()->callMethodSynchronous(
        'file.download',
        array(
          'phid' => $macro['filePHID'],
        ));
      $image = base64_decode($image);
    } catch (Exception $ex) {
      return false;
    }

    if (!$image) {
      return false;
    }

    $img = @imagecreatefromstring($image);
    if (!$img) {
      return false;
    }

    $sx = imagesx($img);
    $sy = imagesy($img);

    if ($sx > $size || $sy > $size) {
      $scale = max($sx, $sy) / $size;
      $dx = floor($sx / $scale);
      $dy = floor($sy / $scale);
    } else {
      $dx = $sx;
      $dy = $sy;
    }

    $dy = floor($dy * $aspect);

    $dst = imagecreatetruecolor($dx, $dy);
    if (!$dst) {
      return false;
    }
    imagealphablending($dst, false);

    $ok = imagecopyresampled(
      $dst, $img,
      0, 0,
      0, 0,
      $dx, $dy,
      $sx, $sy);

    if (!$ok) {
      return false;
    }

    $map = array(
      ' ',
      '.',
      ',',
      ':',
      ';',
      '!',
      '|',
      '*',
      '=',
      '@',
      '$',
      '#',
    );

    $lines = array();

    for ($ii = 0; $ii < $dy; $ii++) {
      $buf = '';
      for ($jj = 0; $jj < $dx; $jj++) {
        $c = imagecolorat($dst, $jj, $ii);

        $a = ($c >> 24) & 0xFF;
        $r = ($c >> 16) & 0xFF;
        $g = ($c >> 8) & 0xFF;
        $b = ($c) & 0xFF;

        $luma = (255 - ((0.30 * $r) + (0.59 * $g) + (0.11 * $b))) / 256;
        $luma *= ((127 - $a) / 127);

        $char = $map[max(0, floor($luma * count($map)))];
        $buf .= $char;
      }

      $lines[] = $buf;
    }

    return $lines;
  }

}
