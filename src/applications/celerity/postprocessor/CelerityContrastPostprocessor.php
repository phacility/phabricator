<?php

final class CelerityContrastPostprocessor
  extends CelerityPostprocessor {

  public function getPostprocessorKey() {
    return 'icontrast';
  }

  public function getPostprocessorName() {
    return pht('Inverted Contrast Colors');
  }

  public function buildVariables() {
    return array(
      'blue' => '#2889c6',
      'sky' => '#2889c6',
      'anchor' => '#2889c6',

       //Theme phacility
       'bg-dark-grey'       => '#000',
       'bg-menu'            => '#000',
       'defaulttext'          => '#fff',
      
      'thinblueborder' => '#3f4447',
      'lightblueborder' => '#444a5a',
      'blueborder' => '#91a3c1',

      'lightgreyborder' => '#bdbdbd',
      'greyborder' => '#d7d7d7',

      'lightbluetext' => '#bdbdbd',
      'bluetext' => '#d7d7d7',
      'darkbluetext' => '#fff',

      'lightgreytext' => '#bdbdbd', 
      'greytext' => '#d7d7d7',
      'darkgreytext' => '#fff',

      'sh-redtext' => '#d7d7d7', 
      'sh-redborder' => '#a2a2a2', 

      'sh-greentext' => '#d7d7d7',
      'sh-greenborder' => '#a2a2a2',

      'sh-bluetext' => '#d7d7d7',
      'sh-blueborder' => '#a2a2a2',

      'sh-yellowtext' => '#d7d7d7',
      'sh-yellowborder' => '#a2a2a2',

      'sh-orangetext' => '#d7d7d7',
      'sh-orangeborder' => '#a2a2a2',

      'sh-violettext' => '#d7d7d7',
      'sh-violetborder' => '#a2a2a2',

      'sh-indigotext' => '#d7d7d7',
      'sh-indigoborder' => '#a2a2a2',

      'sh-pinktext' => '#d7d7d7',
      'sh-pinkborder' => '#a2a2a2',

      'sh-greytext' => '#d7d7d7',
      'sh-greyborder' => '#a2a2a2',

      'sh-disabledtext' => '#bdbdbd',
      'sh-disabledborder' => '#a2a2a2',


    );
  }

}
