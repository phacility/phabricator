<?php

final class AphrontHeadsupActionView extends AphrontView {

  private $name;
  private $class;
  private $uri;
  private $workflow;
  private $instant;
  private $user;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function setClass($class) {
    $this->class = $class;
    return $this;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function setInstant($instant) {
    $this->instant = $instant;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    if ($this->instant) {
      $button_class = $this->class.' link';
      return phabricator_render_form(
        $this->user,
        array(
          'action' => $this->uri,
          'method' => 'post',
          'style'  => 'display: inline',
        ),
        '<button class="'.$button_class.'">'.
          phutil_escape_html($this->name).
        '</button>'
      );
    }

    if ($this->uri) {
      $tag = 'a';
    } else {
      $tag = 'span';
    }

    $attrs = array(
      'href' => $this->uri,
      'class' => $this->class,
    );

    if ($this->workflow) {
      $attrs['sigil'] = 'workflow';
    }

    return javelin_render_tag(
      $tag,
      $attrs,
      phutil_escape_html($this->name));
  }

}
