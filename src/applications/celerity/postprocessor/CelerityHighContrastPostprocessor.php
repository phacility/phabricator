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
      'blue' => '#226B9B',
      'sky' => '#226B9B',
      'anchor' => '#226B9B',

      'thinblueborder' => '#BFCFDA',
      'lightblueborder' => '#8C98B8',
      'blueborder' => '#626E82',
      'timeline' => '#8C98B8',

      'lightgreyborder' => '#555',
      'greyborder' => '#333',

      'lightbluetext' => '#555',
      'bluetext' => '#333',
      'darkbluetext' => '#000',

      'lightgreytext' => '#555',
      'greytext' => '#333',
      'darkgreytext' => '#000',

      'sh-redtext' => '#333',
      'sh-redborder' => '#777',

      'sh-greentext' => '#333',
      'sh-greenborder' => '#777',

      'sh-bluetext' => '#333',
      'sh-blueborder' => '#777',

      'sh-yellowtext' => '#333',
      'sh-yellowborder' => '#777',

      'sh-orangetext' => '#333',
      'sh-orangeborder' => '#777',

      'sh-violettext' => '#333',
      'sh-violetborder' => '#777',

      'sh-indigotext' => '#333',
      'sh-indigoborder' => '#777',

      'sh-pinktext' => '#333',
      'sh-pinkborder' => '#777',

      'sh-greytext' => '#333',
      'sh-greyborder' => '#777',

      'sh-disabledtext' => '#555',
      'sh-disabledborder' => '#777',


    );
  }

}
