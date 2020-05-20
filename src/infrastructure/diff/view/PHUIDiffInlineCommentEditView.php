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
        $this->renderInputs(),
        $this->renderBody(),
      ));

    return $content;
  }

  private function renderInputs() {
    $inputs = array();
    $inline = $this->getInlineComment();

    $inputs[] = array('op', 'edit');
    $inputs[] = array('id', $inline->getID());

    $inputs[] = array('on_right', $this->getIsOnRight());
    $inputs[] = array('renderer', $this->getRenderer());

    $out = array();

    foreach ($inputs as $input) {
      list($name, $value) = $input;
      $out[] = phutil_tag(
        'input',
        array(
          'type'  => 'hidden',
          'name'  => $name,
          'value' => $value,
        ));
    }

    return $out;
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

    $body = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-body',
      ),
      $this->newTextarea());

    $edit = phutil_tag(
      'div',
      array(
        'class' => 'differential-inline-comment-edit-buttons grouped',
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

    $text = $inline->getContentForEdit($viewer);

    return id(new PhabricatorRemarkupControl())
      ->setViewer($viewer)
      ->setSigil('differential-inline-comment-edit-textarea')
      ->setName('text')
      ->setValue($text)
      ->setDisableFullScreen(true);
  }

}
