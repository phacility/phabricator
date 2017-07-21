<?php

final class CelerityDarkModePostprocessor
  extends CelerityPostprocessor {

  public function getPostprocessorKey() {
    return 'darkmode';
  }

  public function getPostprocessorName() {
    return pht('Dark Mode (Experimental)');
  }

  public function buildVariables() {
    return array(

      // Fonts
      'basefont' => "13px 'Segoe UI', 'Segoe UI Emoji', ".
        "'Segoe UI Symbol', 'Lato', 'Helvetica Neue', ".
        "Helvetica, Arial, sans-serif",

      'fontfamily' => "'Segoe UI', 'Segoe UI Emoji', ".
        "'Segoe UI Symbol', 'Lato', 'Helvetica Neue', ".
        "Helvetica, Arial, sans-serif",

      // Drop Shadow
      'dropshadow' => '0 2px 12px rgba(0, 0, 0, .20)',
      'whitetextshadow' => '0 1px 0 rgba(255, 255, 255, 1)',

      // Anchors
      'anchor' => '#3498db',

      // Base Colors
      'red'           => '#c0392b',
      'lightred'      => '#7f261c',
      'orange'        => '#e67e22',
      'lightorange'   => '#f7e2d4',
      'yellow'        => '#f1c40f',
      'lightyellow'   => '#a4850a',
      'green'         => '#139543',
      'lightgreen'    => '#0e7032',
      'blue'          => '#2980b9',
      'lightblue'     => '#1d5981',
      'sky'           => '#3498db',
      'lightsky'      => '#ddeef9',
      'fire'          => '#e62f17',
      'indigo'        => '#6e5cb6',
      'lightindigo'   => '#eae6f7',
      'pink'          => '#da49be',
      'lightpink'     => '#fbeaf8',
      'violet'        => '#8e44ad',
      'lightviolet'   => '#622f78',
      'charcoal'      => '#4b4d51',
      'backdrop'      => '#c4cde0',
      'hoverwhite'    => 'rgba(255,255,255,.6)',
      'hovergrey'     => '#c5cbcf',
      'hoverblue'     => '#2a425f',
      'hoverborder'   => '#dfe1e9',
      'hoverselectedgrey' => '#bbc4ca',
      'hoverselectedblue' => '#e6e9ee',
      'borderinset' => 'inset 0 0 0 1px rgba(55,55,55,.15)',
      'timeline'    => '#4e6078',
      'timeline.icon.background' => '#416086',
      'bluepropertybackground' => '#2d435f',

      // Alphas
      'alphawhite'          => '255,255,255',
      'alphagrey'           => '255,255,255',
      'alphablue'           => '255,255,255',
      'alphablack'          => '0,0,0',

      // Base Greys
      'lightgreyborder'     => 'rgba(255,255,255,.3)',
      'greyborder'          => 'rgba(255,255,255,.6)',
      'darkgreyborder'      => 'rgba(255,255,255,.9)',
      'lightgreytext'       => 'rgba(255,255,255,.3)',
      'greytext'            => 'rgba(255,255,255,.6)',
      'darkgreytext'        => 'rgba(255,255,255,.9)',
      'lightgreybackground' => '#2a425f',
      'greybackground'      => '#304a6d',
      'darkgreybackground'  => '#8C98B8',

      // Base Blues
      'thinblueborder'      => '#2c405a',
      'lightblueborder'     => '#39506d',
      'blueborder'          => '#8C98B8',
      'darkblueborder'      => '#626E82',
      'lightbluebackground' => 'rgba(255,255,255,.05)',
      'bluebackground'      => 'rgba(255,255,255,.1)',
      'lightbluetext'       => 'rgba(255,255,255,.3)',
      'bluetext'            => 'rgba(255,255,255,.6)',
      'darkbluetext'        => 'rgba(255,255,255,.8)',
      'blacktext'           => 'rgba(255,255,255,.9)',

      // Base Greens
      'lightgreenborder'      => '#bfdac1',
      'greenborder'           => '#8cb89c',
      'greentext'             => '#3e6d35',
      'lightgreenbackground'  => '#e6f2e4',

      // Base Red
      'lightredborder'        => '#f4c6c6',
      'redborder'             => '#eb9797',
      'redtext'               => '#802b2b',
      'lightredbackground'    => '#f5e1e1',

      // Base Violet
      'lightvioletborder'     => '#cfbddb',
      'violetborder'          => '#b589ba',
      'violettext'            => '#603c73',
      'lightvioletbackground' => '#e9dfee',

      // Shades are a more muted set of our base colors
      // better suited to blending into other UIs.

      // Shade Red
      'sh-lightredborder'     => '#efcfcf',
      'sh-redborder'          => '#d1abab',
      'sh-redicon'            => '#c85a5a',
      'sh-redtext'            => '#a53737',
      'sh-redbackground'      => '#f7e6e6',

      // Shade Orange
      'sh-lightorangeborder'  => '#f8dcc3',
      'sh-orangeborder'       => '#dbb99e',
      'sh-orangeicon'         => '#e78331',
      'sh-orangetext'         => '#ba6016',
      'sh-orangebackground'   => '#fbede1',

      // Shade Yellow
      'sh-lightyellowborder'  => '#e9dbcd',
      'sh-yellowborder'       => '#c9b8a8',
      'sh-yellowicon'         => '#9b946e',
      'sh-yellowtext'         => '#726f56',
      'sh-yellowbackground'   => '#fdf3da',

      // Shade Green
      'sh-lightgreenborder'   => '#c6e6c7',
      'sh-greenborder'        => '#a0c4a1',
      'sh-greenicon'          => '#4ca74e',
      'sh-greentext'          => '#326d34',
      'sh-greenbackground'    => '#ddefdd',

      // Shade Blue
      'sh-lightblueborder'    => '#cfdbe3',
      'sh-blueborder'         => '#a7b5bf',
      'sh-blueicon'           => '#6b748c',
      'sh-bluetext'           => '#464c5c',
      'sh-bluebackground'     => '#dee7f8',

      // Shade Indigo
      'sh-lightindigoborder'  => '#d1c9ee',
      'sh-indigoborder'       => '#bcb4da',
      'sh-indigoicon'         => '#8672d4',
      'sh-indigotext'         => '#6e5cb6',
      'sh-indigobackground'   => '#eae6f7',

      // Shade Violet
      'sh-lightvioletborder'  => '#e0d1e7',
      'sh-violetborder'       => '#bcabc5',
      'sh-violeticon'         => '#9260ad',
      'sh-violettext'         => '#69427f',
      'sh-violetbackground'   => '#efe8f3',

      // Shade Pink
      'sh-lightpinkborder'  => '#f6d5ef',
      'sh-pinkborder'       => '#d5aecd',
      'sh-pinkicon'         => '#e26fcb',
      'sh-pinktext'         => '#da49be',
      'sh-pinkbackground'   => '#fbeaf8',

      // Shade Grey
      'sh-lightgreyborder'    => '#e3e4e8',
      'sh-greyborder'         => '#b2b2b2',
      'sh-greyicon'           => '#757575',
      'sh-greytext'           => '#555555',
      'sh-greybackground'     => '#edeef2',

      // Shade Disabled
      'sh-lightdisabledborder'  => '#e5e5e5',
      'sh-disabledborder'       => '#cbcbcb',
      'sh-disabledicon'         => '#bababa',
      'sh-disabledtext'         => '#a6a6a6',
      'sh-disabledbackground'   => '#f3f3f3',

      // Diffs
      'diff.background' => '#121b27',
      'new-background' => 'rgba(151, 234, 151, .55)',
      'new-bright' => 'rgba(151, 234, 151, .75)',
      'old-background' => 'rgba(251, 175, 175, .55)',
      'old-bright' => 'rgba(251, 175, 175, .8)',
      'move-background' => '#faca00',
      'copy-background' => '#f1c40f',

      // Usually light yellow
      'gentle.highlight' => '#26c1c9',
      'gentle.highlight.border' => '#21a9b0',

      'paste.content' => '#222222',
      'paste.border' => '#000000',
      'paste.highlight' => '#121212',

      // Background color for "most" themes.
      'page.background' => '#223246',
      'page.sidenav' => '#1c293b',
      'page.content' => '#26374c',

      'menu.profile.text' => 'rgba(255,255,255,.8)',
      'menu.profile.text.selected' => 'rgba(255,255,255,1)',
      'menu.profile.icon.disabled' => 'rgba(255,255,255,.4)',

      // Buttons
      'blue.button.color' => '#2980b9',
      'blue.button.gradient' => 'linear-gradient(to bottom, #3498db, #2980b9)',
      'green.button.color' => '#139543',
      'green.button.gradient' => 'linear-gradient(to bottom, #23BB5B, #139543)',
      'grey.button.color' => '#223246',
      'grey.button.gradient' => 'linear-gradient(to bottom, #223246, #223246)',
      'grey.button.hover' => 'linear-gradient(to bottom, #1c293b, #1c293b)',

    );
  }

}
