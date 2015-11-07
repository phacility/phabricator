<?php

final class PhabricatorHTTPParameterTypeTableView
  extends AphrontView {

  private $types;

  public function setHTTPParameterTypes(array $types) {
    assert_instances_of($types, 'AphrontHTTPParameterType');
    $this->types = $types;
    return $this;
  }

  public function getHTTPParameterTypes() {
    return $this->types;
  }

  public function render() {
    $types = $this->getHTTPParameterTypes();
    $types = mpull($types, null, 'getTypeName');

    $br = phutil_tag('br');

    $rows = array();
    foreach ($types as $name => $type) {
      $formats = $type->getFormatDescriptions();
      $formats = phutil_implode_html($br, $formats);

      $examples = $type->getExamples();
      $examples = phutil_implode_html($br, $examples);

      $rows[] = array(
        $name,
        $formats,
        $examples,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Type'),
          pht('Formats'),
          pht('Examples'),
        ))
      ->setColumnClasses(
        array(
          'pri top',
          'top',
          'wide top prewrap',
        ));

    return $table;
  }

}
