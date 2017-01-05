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
      'color: #333333;',
      'background: #f8cbcb;',
    );
    $old_styles = implode(' ', $old_styles);

    $new_styles = array(
      'padding: 0 2px;',
      'color: #333333;',
      'background: #a6f3a6;',
    );
    $new_styles = implode(' ', $new_styles);

    $omit_styles = array(
      'padding: 8px 0;',
    );
    $omit_styles = implode(' ', $omit_styles);

    $result = array();
    foreach ($diff->getSummaryParts() as $part) {
      $type = $part['type'];
      $text = $part['text'];
      switch ($type) {
        case '.':
          $result[] = phutil_tag(
            'div',
            array(
              'style' => $omit_styles,
            ),
            pht('...'));
          break;
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
      'color: #74777D;',
    );

    // Beyond applying "pre-wrap", convert newlines to "<br />" explicitly
    // to improve behavior in clients like Airmail.
    $result = phutil_escape_html_newlines($result);

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

    $diff_view = phutil_tag(
      'div',
      array(
        'class' => 'prose-diff',
      ),
      $result);

    $old_view = phutil_tag(
      'div',
      array(
        'class' => 'prose-diff',
      ),
      $this->oldText);

    $new_view = phutil_tag(
      'div',
      array(
        'class' => 'prose-diff',
      ),
      $this->newText);

    return id(new PHUITabGroupView())
      ->addTab(
        id(new PHUITabView())
          ->setKey('old')
          ->setName(pht('Old'))
          ->appendChild($old_view))
      ->addTab(
        id(new PHUITabView())
          ->setKey('new')
          ->setName(pht('New'))
          ->appendChild($new_view))
      ->addTab(
        id(new PHUITabView())
          ->setKey('diff')
          ->setName(pht('Diff'))
          ->appendChild($diff_view))
      ->selectTab('diff');
  }

  private function buildDiff() {
    $engine = new PhutilProseDifferenceEngine();
    return $engine->getDiff($this->oldText, $this->newText);
  }

}
