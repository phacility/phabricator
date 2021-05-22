<?php

final class PHUIDiffInlineCommentEditView
  extends PHUIDiffInlineCommentView {

  private $title;

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function render() {
    $viewer = $this->getViewer();
    $inline = $this->getInlineComment();

    $content = phabricator_form(
      $viewer,
      array(
        'action' => $inline->getControllerURI(),
        'method' => 'POST',
        'sigil' => 'inline-edit-form',
      ),
      array(
        $this->renderBody(),
      ));

    return $content;
  }

  private function renderBody() {
    $buttons = array();

    $buttons[] = id(new PHUIButtonView())
      ->setText(pht('Save Draft'));

    $buttons[] = id(new PHUIButtonView())
      ->setText(pht('Cancel'))
      ->setColor(PHUIButtonView::GREY)
      ->addSigil('inline-edit-cancel');

    $title = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-title',
      ),
      $this->title);

    $corpus_view = $this->newCorpusView();

    $body = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-body',
      ),
      array(
        $corpus_view,
        $this->newTextarea(),
      ));

    $edit = javelin_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-buttons grouped',
        'sigil' => 'inline-edit-buttons',
      ),
      array(
        $buttons,
      ));

    $inline = $this->getInlineComment();

    return javelin_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit',
        'sigil' => 'differential-inline-comment',
        'meta' => $this->getInlineCommentMetadata(),
      ),
      array(
        $title,
        $body,
        $edit,
      ));
  }

  private function newTextarea() {
    $viewer = $this->getViewer();
    $inline = $this->getInlineComment();

    $state = $inline->getContentStateForEdit($viewer);

    return id(new PhabricatorRemarkupControl())
      ->setViewer($viewer)
      ->setSigil('inline-content-text')
      ->setValue($state->getContentText())
      ->setDisableFullScreen(true);
  }

  private function newCorpusView() {
    $viewer = $this->getViewer();
    $inline = $this->getInlineComment();

    $context = $inline->getInlineContext();
    if ($context === null) {
      return null;
    }

    $head = $context->getHeadLines();
    $head = $this->newContextView($head);

    $state = $inline->getContentStateForEdit($viewer);

    $main = $state->getContentSuggestionText();
    $main_count = count(phutil_split_lines($main));

    // Browsers ignore one leading newline in text areas. Add one so that
    // any actual leading newlines in the content are preserved.
    $main = "\n".$main;

    $textarea = javelin_tag(
      'textarea',
      array(
        'class' => 'inline-suggestion-input PhabricatorMonospaced',
        'rows' => max(3, $main_count + 1),
        'sigil' => 'inline-content-suggestion',
      ),
      $main);

    $main = phutil_tag(
      'tr',
      array(
        'class' => 'inline-suggestion-input-row',
      ),
      array(
        phutil_tag(
          'td',
          array(
            'class' => 'inline-suggestion-line-cell',
          ),
          null),
        phutil_tag(
          'td',
          array(
            'class' => 'inline-suggestion-input-cell',
          ),
          $textarea),
      ));

    $tail = $context->getTailLines();
    $tail = $this->newContextView($tail);

    $body = phutil_tag(
      'tbody',
      array(),
      array(
        $head,
        $main,
        $tail,
      ));

    $table = phutil_tag(
      'table',
      array(
        'class' => 'inline-suggestion-table',
      ),
      $body);

    $container = phutil_tag(
      'div',
      array(
        'class' => 'inline-suggestion',
      ),
      $table);

    return $container;
  }

  private function newContextView(array $lines) {
    if (!$lines) {
      return array();
    }

    $rows = array();
    foreach ($lines as $index => $line) {
      $line_cell = phutil_tag(
        'td',
        array(
          'class' => 'inline-suggestion-line-cell PhabricatorMonospaced',
        ),
        $index + 1);

      $text_cell = phutil_tag(
        'td',
        array(
          'class' => 'inline-suggestion-text-cell PhabricatorMonospaced',
        ),
        $line);

      $cells = array(
        $line_cell,
        $text_cell,
      );

      $rows[] = phutil_tag('tr', array(), $cells);
    }

    return $rows;
  }

}
