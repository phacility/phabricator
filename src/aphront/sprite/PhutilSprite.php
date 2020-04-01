<?php

/**
 * NOTE: This is very new and unstable.
 */
final class PhutilSprite extends Phobject {

  private $sourceFiles = array();
  private $sourceX;
  private $sourceY;
  private $sourceW;
  private $sourceH;
  private $targetCSS;
  private $spriteSheet;
  private $name;

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setTargetCSS($target_css) {
    $this->targetCSS = $target_css;
    return $this;
  }

  public function getTargetCSS() {
    return $this->targetCSS;
  }

  public function setSourcePosition($x, $y) {
    $this->sourceX = $x;
    $this->sourceY = $y;
    return $this;
  }

  public function setSourceSize($w, $h) {
    $this->sourceW = $w;
    $this->sourceH = $h;
    return $this;
  }

  public function getSourceH() {
    return $this->sourceH;
  }

  public function getSourceW() {
    return $this->sourceW;
  }

  public function getSourceY() {
    return $this->sourceY;
  }

  public function getSourceX() {
    return $this->sourceX;
  }

  public function setSourceFile($source_file, $scale = 1) {
    $this->sourceFiles[$scale] = $source_file;
    return $this;
  }

  public function getSourceFile($scale) {
    if (empty($this->sourceFiles[$scale])) {
      throw new Exception(pht("No source file for scale '%s'!", $scale));
    }

    return $this->sourceFiles[$scale];
  }

}
