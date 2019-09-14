<?php

final class PhabricatorPDFContentsObject
  extends PhabricatorPDFObject {

  private $rawContent;

  public function setRawContent($raw_content) {
    $this->rawContent = $raw_content;
    return $this;
  }

  public function getRawContent() {
    return $this->rawContent;
  }

  protected function writeObject() {
    $data = $this->getRawContent();

    $stream_length = $this->newStream($data);

    $this->writeLine('/Filter /FlateDecode /Length %d', $stream_length);
  }

}
