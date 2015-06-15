<?php

final class DiffusionFileContent extends Phobject {

  private $corpus;
  private $blameDict;
  private $revList;
  private $textList;

  public function setTextList(array $text_list) {
    $this->textList = $text_list;
    return $this;
  }
  public function getTextList() {
    if (!$this->textList) {
      return phutil_split_lines($this->getCorpus(), $retain_ends = false);
    }
    return $this->textList;
  }

  public function setRevList(array $rev_list) {
    $this->revList = $rev_list;
    return $this;
  }
  public function getRevList() {
    return $this->revList;
  }

  public function setBlameDict(array $blame_dict) {
    $this->blameDict = $blame_dict;
    return $this;
  }
  public function getBlameDict() {
    return $this->blameDict;
  }

  public function setCorpus($corpus) {
    $this->corpus = $corpus;
    return $this;
  }

  public function getCorpus() {
    return $this->corpus;
  }

  public function toDictionary() {
    return array(
      'corpus' => $this->getCorpus(),
      'blameDict' => $this->getBlameDict(),
      'revList' => $this->getRevList(),
      'textList' => $this->getTextList(),
    );
  }

  public static function newFromConduit(array $dict) {
    return id(new DiffusionFileContent())
      ->setCorpus($dict['corpus'])
      ->setBlameDict($dict['blameDict'])
      ->setRevList($dict['revList'])
      ->setTextList($dict['textList']);
  }

}
