<?php


class EmailCommentMessage {
  public string $asText;
  public string $asHtml;

  public function __construct(string $asText, string $asHtml) {
    $this->asText = $asText;
    $this->asHtml = $asHtml;
  }
}