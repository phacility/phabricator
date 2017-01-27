<?php

final class PhabricatorEmojiDatasource extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type an emoji name...');
  }

  public function getBrowseTitle() {
    return pht('Browse Emojis');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorMacroApplication';
  }

  public function loadResults() {
    $results = $this->buildResults();
    return $this->filterResultsAgainstTokens($results);
  }

  protected function renderSpecialTokens(array $values) {
    return $this->renderTokensFromResults($this->buildResults(), $values);
  }

  private function buildResults() {
    $raw_query = $this->getRawQuery();

    $data = id(new PhabricatorEmojiRemarkupRule())->markupEmojiJSON();
    $emojis = phutil_json_decode($data);

    $results = array();
    foreach ($emojis as $shortname => $emoji) {
      $display_name = $emoji.' '.$shortname;
      $name = str_replace('_', ' ', $shortname);
      $result = id(new PhabricatorTypeaheadResult())
        ->setPHID($shortname)
        ->setName($name)
        ->setDisplayname($display_name)
        ->setAutocomplete($emoji);

      $results[$shortname] = $result;
    }
    return $results;
  }

}
