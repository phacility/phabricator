<?php

final class AphrontRemarkupHTTPParameterType
  extends AphrontHTTPParameterType {

  protected function getParameterDefault() {
    return $this->newRemarkupValue();
  }

  protected function getParameterValue(AphrontRequest $request, $key) {
    $corpus_key = $key;
    $corpus_type = new AphrontStringHTTPParameterType();
    $corpus_value = $this->getValueWithType(
      $corpus_type,
      $request,
      $corpus_key);

    $metadata_key = $key.'_metadata';
    $metadata_type = new AphrontJSONHTTPParameterType();
    $metadata_value = $this->getValueWithType(
      $metadata_type,
      $request,
      $metadata_key);

    return $this->newRemarkupValue()
      ->setCorpus($corpus_value)
      ->setMetadata($metadata_value);
  }

  protected function getParameterTypeName() {
    return 'string (remarkup)';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Remarkup text.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      'v=Lorem...',
    );
  }

  private function newRemarkupValue() {
    return new RemarkupValue();
  }

}
