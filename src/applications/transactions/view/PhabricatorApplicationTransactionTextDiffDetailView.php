<?php

final class PhabricatorApplicationTransactionTextDiffDetailView
  extends AphrontView {

  private $oldText;
  private $newText;

  public function setNewText($new_text) {
    $this->newText = $new_text;
    return $this;
  }

  public function setOldText($old_text) {
    $this->oldText = $old_text;
    return $this;
  }

  public function renderForMail() {
    $diff = $this->buildDiff();

    $old_styles = array(
      'padding: 0 2px;',
      'color: #802b2b;',
      'background: rgba(251, 175, 175, .7);',
    );
    $old_styles = implode(' ', $old_styles);

    $new_styles = array(
      'padding: 0 2px;',
      'color: #3e6d35;',
      'background: rgba(151, 234, 151, .6);',
    );
    $new_styles = implode(' ', $new_styles);

    $result = array();
    foreach ($diff->getParts() as $part) {
      $type = $part['type'];
      $text = $part['text'];
      switch ($type) {
        case '-':
          $result[] = phutil_tag(
            'span',
            array(
              'style' => $old_styles,
            ),
            $text);
          break;
        case '+':
          $result[] = phutil_tag(
            'span',
            array(
              'style' => $new_styles,
            ),
            $text);
          break;
        case '=':
          $result[] = $text;
          break;
      }
    }

    $styles = array(
      'white-space: pre-wrap;',
    );

    return phutil_tag(
      'div',
      array(
        'style' => implode(' ', $styles),
      ),
      $result);
  }

  public function render() {
    $diff = $this->buildDiff();

    require_celerity_resource('differential-changeset-view-css');

    $result = array();
    foreach ($diff->getParts() as $part) {
      $type = $part['type'];
      $text = $part['text'];
      switch ($type) {
        case '-':
          $result[] = phutil_tag(
            'span',
            array(
              'class' => 'old',
            ),
            $text);
          break;
        case '+':
          $result[] = phutil_tag(
            'span',
            array(
              'class' => 'new',
            ),
            $text);
          break;
        case '=':
          $result[] = $text;
          break;
      }
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'prose-diff',
      ),
      $result);
  }

  private function buildDiff() {
    $engine = new PhutilProseDifferenceEngine();
    return $engine->getDiff($this->oldText, $this->newText);
  }

}
