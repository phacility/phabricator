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
