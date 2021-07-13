<?php


class EmailBug {
  public int $bugId;
  public string $name;
  public string $link;

  public function __construct(int $bugId, string $name, string $link) {
    $this->bugId = $bugId;
    $this->name = $name;
    $this->link = $link;
  }
}