<?php

final class ConduitWildParameterType
  extends ConduitListParameterType {

  protected function getParameterTypeName() {
    return 'wild';
  }

  protected function getParameterFormatDescriptions() {
    return array(
      pht('Any mixed or complex value. Check the documentation for details.'),
    );
  }

  protected function getParameterExamples() {
    return array(
      pht('(Wildcard)'),
    );
  }

}
