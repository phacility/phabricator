<?php

final class PhutilRemarkupNoteBlockRule extends PhutilRemarkupBlockRule {

  public function getMatchingLineCount(array $lines, $cursor) {
    $num_lines = 0;

    if (preg_match($this->getRegEx(), $lines[$cursor])) {
      $num_lines++;
      $cursor++;

      while (isset($lines[$cursor])) {
        if (trim($lines[$cursor])) {
          $num_lines++;
          $cursor++;
          continue;
        }
        break;
      }
    }

    return $num_lines;
  }

  public function markupText($text, $children) {
    $matches = array();
    preg_match($this->getRegEx(), $text, $matches);

    if (idx($matches, 'showword')) {
      $word = $matches['showword'];
      $show = true;
    } else {
      $word = $matches['hideword'];
      $show = false;
    }

    $class_suffix = phutil_utf8_strtolower($word);

    // This is the "(IMPORTANT)" or "NOTE:" part.
    $word_part = rtrim(substr($text, 0, strlen($matches[0])));

    // This is the actual text.
    $text_part = substr($text, strlen($matches[0]));
    $text_part = $this->applyRules(rtrim($text_part));

    $text_mode = $this->getEngine()->isTextMode();
    $html_mail_mode = $this->getEngine()->isHTMLMailMode();
    if ($text_mode) {
      return $word_part.' '.$text_part;
    }

    if ($show) {
      $content = array(
        phutil_tag(
          'span',
          array(
            'class' => 'remarkup-note-word',
          ),
          $word_part),
        ' ',
        $text_part,
      );
    } else {
      $content = $text_part;
    }

    if ($html_mail_mode) {
      if ($class_suffix == 'important') {
        $attributes = array(
          'style' => 'margin: 16px 0;
            padding: 12px;
            border-left: 3px solid #c0392b;
            background: #f4dddb;',
        );
      } else if ($class_suffix == 'note') {
        $attributes = array(
          'style' => 'margin: 16px 0;
            padding: 12px;
            border-left: 3px solid #2980b9;
            background: #daeaf3;',
        );
      } else if ($class_suffix == 'warning') {
        $attributes = array(
          'style' => 'margin: 16px 0;
            padding: 12px;
            border-left: 3px solid #f1c40f;
            background: #fdf5d4;',
        );
      }
    } else {
      $attributes = array(
        'class' => 'remarkup-'.$class_suffix,
      );
    }

    return phutil_tag(
      'div',
      $attributes,
      $content);
  }

  private function getRegEx() {
    static $regex;

    if ($regex === null) {
      $words = array(
        'NOTE',
        'IMPORTANT',
        'WARNING',
      );

      foreach ($words as $k => $word) {
        $words[$k] = preg_quote($word, '/');
      }
      $words = implode('|', $words);

      $regex =
        '/^(?:'.
        '(?:\((?P<hideword>'.$words.')\))'.
        '|'.
        '(?:(?P<showword>'.$words.'):))\s*'.
        '/';
    }

    return $regex;
  }
}
