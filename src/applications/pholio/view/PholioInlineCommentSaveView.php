<?php

/**
 * @group pholio
 */
final class PholioInlineCommentSaveView extends AphrontView {

  private $inputs = array();
  private $uri;
  private $title;

  public function addHiddenInput($key, $value) {
    $this->inputs[] = array($key, $value);
    return $this;
  }

  public function setSubmitURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function render() {
    if (!$this->uri) {
      throw new Exception("Call setSubmitURI() before render()!");
    }
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    $content = phabricator_form(
      $this->user,
      array(
        'action'    => $this->uri,
        'method'    => 'POST',
        'sigil'     => 'inline-save-form',
        'class'     => 'pholio-new-inline-comment',
        'id'        => 'pholio-new-inline-comment-dialog'
      ),
      array(
        $this->renderInputs(),
        $this->renderBody(),
      ));

    return $content;
  }

  private function renderInputs() {
    $out = array();
    foreach ($this->inputs as $input) {
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

    $buttons[] = javelin_tag(
      'button',
      array(
        'sigil' => 'inline-save-submit',
      ),
      pht('Done'));
    $buttons[] = javelin_tag(
      'button',
      array(
        'sigil' => 'inline-save-cancel',
        'class' => 'grey',
      ),
      pht('Cancel'));

    $formatting = phutil_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/Remarkup_Reference.html'),
        'tabindex' => '-1',
        'target' => '_blank',
      ),
      pht('Formatting Reference'));

    $title = phutil_tag(
      'div',
      array(
        'class' => 'pholio-inline-comment-dialog-title',
      ),
      $this->title);

    $body = phutil_tag(
      'div',
      array(),
      $this->renderChildren());

    $edit = phutil_tag(
      'edit',
      array(
        'class' => 'pholio-inline-comment-dialog-buttons',
      ),
      array(
        $formatting,
        $buttons,
        phutil_tag('div', array('style' => 'clear: both'), ''),
      ));

    return javelin_tag(
      'div',
      array(
      ),
      array(
        $title,
        $body,
        $edit,
      ));
  }

}
