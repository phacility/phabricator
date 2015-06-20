<?php

final class CelerityHighContrastPostprocessor
  extends CelerityPostprocessor {

  public function getPostprocessorKey() {
    return 'contrast';
  }

  public function getPostprocessorName() {
    return pht('Use High Contrast Colors');
  }

  public function buildVariables() {
    return array(
      'page.background.light' => '#dfdfdf',
      'page.background.dark' => '#dfdfdf',

      'lightblueborder' => '#000099',
      'blueborder' => '#000066',

      'lightbluetext' => '#333366',
      'bluetext' => '#222244',
    );
  }

}
