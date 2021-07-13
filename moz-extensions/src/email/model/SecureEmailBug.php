<?php


class SecureEmailBug {
  public int $bugId;
  public string $link;

  public function __construct(int $bugId, string $link) {
    $this->bugId = $bugId;
    $this->link = $link;
  }


}