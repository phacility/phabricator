<?php

final class PhabricatorDiffInlineCommentContentState
  extends PhabricatorInlineCommentContentState {

  private $hasSuggestion = false;
  private $suggestionText = '';

  public function isEmptyContentState() {
    if (!parent::isEmptyContentState()) {
      return false;
    }

    if ($this->getContentHasSuggestion()) {
      if (strlen($this->getContentSuggestionText())) {
        return false;
      }
    }

    return true;
  }

  public function setContentSuggestionText($suggestion_text) {
    $this->suggestionText = $suggestion_text;
    return $this;
  }

  public function getContentSuggestionText() {
    return $this->suggestionText;
  }

  public function setContentHasSuggestion($has_suggestion) {
    $this->hasSuggestion = $has_suggestion;
    return $this;
  }

  public function getContentHasSuggestion() {
    return $this->hasSuggestion;
  }

  public function newStorageMap() {
    return parent::writeStorageMap() + array(
      'hasSuggestion' => $this->getContentHasSuggestion(),
      'suggestionText' => $this->getContentSuggestionText(),
    );
  }

  public function readStorageMap(array $map) {
    $result = parent::readStorageMap($map);

    $has_suggestion = (bool)idx($map, 'hasSuggestion');
    $this->setContentHasSuggestion($has_suggestion);

    $suggestion_text = (string)idx($map, 'suggestionText');
    $this->setContentSuggestionText($suggestion_text);

    return $result;
  }

  protected function newStorageMapFromRequest(AphrontRequest $request) {
    $map = parent::newStorageMapFromRequest($request);

    $map['hasSuggestion'] = (bool)$request->getBool('hasSuggestion');
    $map['suggestionText'] = (string)$request->getStr('suggestionText');

    return $map;
  }

}
