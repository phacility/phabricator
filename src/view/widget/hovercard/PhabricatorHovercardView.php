<?php

/**
 * The default one-for-all hovercard. We may derive from this one to create
 * more specialized ones
 */
final class PhabricatorHovercardView extends AphrontView {

  /**
   * @var PhabricatorObjectHandle
   */
  private $handle;

  private $title = array();
  private $detail;
  private $tags = array();
  private $fields = array();
  private $actions = array();

  private $color = 'green';

  public function setObjectHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function setDetail($detail) {
    $this->detail = $detail;
    return $this;
  }

  public function addField($label, $value) {
    $this->fields[] = array(
      'label' => $label,
      'value' => $value,
    );
    return $this;
  }

  public function addAction($label, $uri, $workflow = false) {
    $this->actions[] = array(
      'label'    => $label,
      'uri'      => $uri,
      'workflow' => $workflow,
    );
    return $this;
  }

  public function addTag(PhabricatorTagView $tag) {
    $this->tags[] = $tag;
    return $this;
  }

  public function setColor($color) {
    $this->color = $color;
    return $this;
  }

  public function render() {
    if (!$this->handle) {
      throw new Exception("Call setObjectHandle() before calling render()!");
    }

    $handle = $this->handle;

    require_celerity_resource("phabricator-hovercard-view-css");

    $title = pht("%s: %s",
      $handle->getTypeName(),
      $this->title ? $this->title : $handle->getName());

    $header = new PhabricatorActionHeaderView();
    $header->setHeaderColor($this->color);
    $header->setHeaderTitle($title);
    if ($this->tags) {
      foreach ($this->tags as $tag) {
        $header->setTag($tag);
      }
    }

    $body = array();
    if ($this->detail) {
      $body[] = hsprintf('<strong>%s</strong>', $this->detail);
    } else {
      // Fallback for object handles
      $body[] = hsprintf('<strong>%s</strong>', $handle->getFullName());
    }

    foreach ($this->fields as $field) {
      $body[] = hsprintf('<b>%s:</b> <span>%s</span>',
        $field['label'], $field['value']);
    }

    $body = phutil_implode_html(phutil_tag('br'), $body);

    if ($handle->getImageURI()) {
      // Probably a user, we don't need to assume something else
      // "Prepend" the image by appending $body
      $body = phutil_tag(
        'div',
        array(
          'class' => 'profile-header-picture-frame',
          'style' => 'background-image: url('.$handle->getImageURI().');',
        ),
        '')
        ->appendHTML($body);
    }

    $buttons = array();

    foreach ($this->actions as $action) {
      $options = array(
        'class' => 'button grey',
        'href'  => $action['uri'],
      );

      if ($action['workflow']) {
        $options['sigil'] = 'workflow';
        $buttons[] = javelin_tag(
          'a',
          $options,
          $action['label']);
      } else {
        $buttons[] = phutil_tag(
          'a',
          $options,
          $action['label']);
      }
    }

    $tail = null;
    if ($buttons) {
      $tail = phutil_tag('div',
        array('class' => 'phabricator-hovercard-tail'),
        $buttons);
    }

    // Assemble container
    // TODO: Add color support
    $content = hsprintf(
      '%s%s%s',
      phutil_tag('div',
        array(
          'class' => 'phabricator-hovercard-head'
        ),
        $header),
      phutil_tag('div',
        array(
          'class' => 'phabricator-hovercard-body'
        ),
        $body),
      $tail);

    $hovercard = phutil_tag("div",
      array(
        "class" => "phabricator-hovercard-container",
      ),
      $content);

    // Wrap for thick border
    // and later the tip at the bottom
    return phutil_tag('div',
      array(
        'class' => 'phabricator-hovercard-wrapper',
      ),
      $hovercard);
  }

}
