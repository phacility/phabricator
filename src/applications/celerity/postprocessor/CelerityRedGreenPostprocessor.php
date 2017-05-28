<?php

final class CelerityRedGreenPostprocessor
  extends CelerityPostprocessor {

  public function getPostprocessorKey() {
    return 'redgreen';
  }

  public function getPostprocessorName() {
    return pht('Use Red/Green (Deuteranopia) Colors');
  }

  public function buildVariables() {
    return array(
      'new-background' => 'rgba(152, 207, 235, .15)',
      'new-bright' => 'rgba(152, 207, 235, .35)',
      'old-background' => 'rgba(250, 212, 175, .3)',
      'old-bright' => 'rgba(250, 212, 175, .55)',
    );
  }

}
