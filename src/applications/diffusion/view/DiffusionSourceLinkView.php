<?php

final class DiffusionSourceLinkView
  extends AphrontView {

  private $repository;
  private $text;
  private $uri;
  private $blob;
  private $blobMap;
  private $refName;
  private $path;
  private $line;
  private $commit;

  public function setRepository($repository) {
    $this->repository = $repository;
    $this->blobMap = null;
    return $this;
  }

  public function getRepository() {
    return $this->repository;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function getText() {
    return $this->text;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setBlob($blob) {
    $this->blob = $blob;
    $this->blobMap = null;
    return $this;
  }

  public function getBlob() {
    return $this->blob;
  }

  public function setRefName($ref_name) {
    $this->refName = $ref_name;
    return $this;
  }

  public function getRefName() {
    return $this->refName;
  }

  public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getPath() {
    return $this->path;
  }

  public function setCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function getCommit() {
    return $this->commit;
  }

  public function setLine($line) {
    $this->line = $line;
    return $this;
  }

  public function getLine() {
    return $this->line;
  }

  public function getDisplayPath() {
    if ($this->path !== null) {
      return $this->path;
    }

    return $this->getBlobPath();
  }

  public function getDisplayRefName() {
    if ($this->refName !== null) {
      return $this->refName;
    }

    return $this->getBlobRefName();
  }

  public function getDisplayCommit() {
    if ($this->commit !== null) {
      return $this->commit;
    }

    return $this->getBlobCommit();
  }

  public function getDisplayLine() {
    if ($this->line !== null) {
      return $this->line;
    }

    return $this->getBlobLine();
  }

  private function getBlobPath() {
    return idx($this->getBlobMap(), 'path');
  }

  private function getBlobRefName() {
    return idx($this->getBlobMap(), 'branch');
  }

  private function getBlobLine() {
    return idx($this->getBlobMap(), 'line');
  }

  private function getBlobCommit() {
    return idx($this->getBlobMap(), 'commit');
  }

  private function getBlobMap() {
    if ($this->blobMap === null) {
      $repository = $this->getRepository();
      $blob = $this->blob;

      if ($repository && ($blob !== null)) {
        $map = DiffusionRequest::parseRequestBlob(
          $blob,
          $repository->supportsRefs());
      } else {
        $map = array();
      }

      $this->blobMap = $map;
    }

    return $this->blobMap;
  }

  public function render() {
    $repository = $this->getRepository();
    $uri = $this->getURI();

    $color = 'blue';
    $icon = 'fa-file-text-o';

    $text = $this->getText();
    if (!strlen($text)) {
      $path = $this->getDisplayPath();

      $line = $this->getDisplayLine();
      if ($line !== null) {
        $path = pht('%s:%s', $path, $line);
      }

      if ($repository) {
        $path = pht('%s %s', $repository->getMonogram(), $path);
      }

      if ($repository && $repository->supportsRefs()) {
        $default_ref = $repository->getDefaultBranch();
      } else {
        $default_ref = null;
      }

      $ref_name = $this->getDisplayRefName();
      if ($ref_name === $default_ref) {
        $ref_name = null;
      }

      $commit = $this->getDisplayCommit();
      if ($ref_name !== null && $commit !== null) {
        $text = pht('%s (on %s at %s)', $path, $ref_name, $commit);
      } else if ($ref_name !== null) {
        $text = pht('%s (on %s)', $path, $ref_name);
      } else if ($commit !== null) {
        $text = pht('%s (at %s)', $path, $commit);
      } else {
        $text = $path;
      }
    }

    return id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setColor($color)
      ->setIcon($icon)
      ->setHref($uri)
      ->setName($text);
  }

}
