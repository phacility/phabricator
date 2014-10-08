<?php

abstract class PhameBlogSkin extends PhabricatorController {

  private $blog;
  private $baseURI;
  private $preview;
  private $specification;

  public function setSpecification(PhameSkinSpecification $specification) {
    $this->specification = $specification;
    return $this;
  }

  public function getSpecification() {
    return $this->specification;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function getPreview() {
    return $this->preview;
  }

  final public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  final public function getURI($path) {
    return $this->baseURI.$path;
  }

  final public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  final public function getBlog() {
    return $this->blog;
  }

}
