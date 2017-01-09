<?php

/**
 * View which renders down to a single tag, and provides common access for tag
 * attributes (setting classes, sigils, IDs, etc).
 */
abstract class AphrontTagView extends AphrontView {

  private $id;
  private $classes = array();
  private $sigils = array();
  private $style;
  private $metadata;
  private $mustCapture;
  private $workflow;

  public function setWorkflow($workflow) {
    $this->workflow = $workflow;
    return $this;
  }

  public function getWorkflow() {
    return $this->workflow;
  }

  public function setMustCapture($must_capture) {
    $this->mustCapture = $must_capture;
    return $this;
  }

  public function getMustCapture() {
    return $this->mustCapture;
  }

  final public function setMetadata(array $metadata) {
    $this->metadata = $metadata;
    return $this;
  }

  final public function getMetadata() {
    return $this->metadata;
  }

  final public function setStyle($style) {
    $this->style = $style;
    return $this;
  }

  final public function getStyle() {
    return $this->style;
  }

  final public function addSigil($sigil) {
    $this->sigils[] = $sigil;
    return $this;
  }

  final public function getSigils() {
    return $this->sigils;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function getClasses() {
    return $this->classes;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function getID() {
    return $this->id;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    return array();
  }

  protected function getTagContent() {
    return $this->renderChildren();
  }

  final public function render() {
    $this->willRender();

    // A tag view may render no tag at all. For example, the HandleListView is
    // a container which renders a tag in HTML mode, but can also render in
    // text mode without producing a tag. When a tag view has no tag name, just
    // return the tag content as though the view did not exist.
    $tag_name = $this->getTagName();
    if ($tag_name === null) {
      return $this->getTagContent();
    }

    $attributes = $this->getTagAttributes();

    $implode = array('class', 'sigil');
    foreach ($implode as $attr) {
      if (isset($attributes[$attr])) {
        if (is_array($attributes[$attr])) {
          $attributes[$attr] = implode(' ', $attributes[$attr]);
        }
      }
    }

    if (!is_array($attributes)) {
      $class = get_class($this);
      throw new Exception(
        pht("View '%s' did not return an array from getTagAttributes()!",
          $class));
    }

    $sigils = $this->sigils;
    if ($this->workflow) {
      $sigils[] = 'workflow';
    }

    $tag_view_attributes = array(
      'id' => $this->id,

      'class' => implode(' ', $this->classes),
      'style' => $this->style,

      'meta' => $this->metadata,
      'sigil' => $sigils ? implode(' ', $sigils) : null,
      'mustcapture' => $this->mustCapture,
    );

    foreach ($tag_view_attributes as $key => $value) {
      if ($value === null) {
        continue;
      }
      if (!isset($attributes[$key])) {
        $attributes[$key] = $value;
        continue;
      }
      switch ($key) {
        case 'class':
        case 'sigil':
          $attributes[$key] = $attributes[$key].' '.$value;
          break;
        default:
          // Use the explicitly set value rather than the tag default value.
          $attributes[$key] = $value;
          break;
      }
    }

    return javelin_tag(
      $tag_name,
      $attributes,
      $this->getTagContent());
  }
}
