<?php

final class CelerityDefaultPostprocessor
  extends CelerityPostprocessor {

  const POSTPROCESSOR_KEY = 'default';

  public function getPostprocessorKey() {
    return self::POSTPROCESSOR_KEY;
  }

  public function getPostprocessorName() {
    return pht('Use Standard Colors');
  }

  public function buildDefaultPostprocessor() {
    return null;
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
      'anchor' => '#136CB2',

      // Font Sizes
      'biggestfontsize' => '15px',
      'biggerfontsize' => '14px',
      'normalfontsize' => '13px',
      'smallerfontsize' => '12px',
      'smallestfontsize' => '11px',

      // Base Colors
      'red'           => '#c0392b',
      'lightred'      => '#f4dddb',
      'orange'        => '#e67e22',
      'lightorange'   => '#f7e2d4',
      'yellow'        => '#f1c40f',
      'lightyellow'   => '#fdf5d4',
      'green'         => '#139543',
      'lightgreen'    => '#d7eddf',
      'blue'          => '#2980b9',
      'lightblue'     => '#daeaf3',
      'sky'           => '#3498db',
      'lightsky'      => '#ddeef9',
      'fire'          => '#e62f17',
      'indigo'        => '#6e5cb6',
      'lightindigo'   => '#eae6f7',
      'pink'          => '#da49be',
      'lightpink'     => '#fbeaf8',
      'violet'        => '#8e44ad',
      'lightviolet'   => '#ecdff1',
      'charcoal'      => '#4b4d51',
      'backdrop'      => '#c4cde0',
      'hoverwhite'    => 'rgba(255,255,255,.6)',
      'hovergrey'     => '#c5cbcf',
      'hoverblue'     => '#eceff5',
      'hoverborder'   => '#dfe1e9',
      'hoverselectedgrey' => '#bbc4ca',
      'hoverselectedblue' => '#e6e9ee',
      'borderinset' => 'inset 0 0 0 1px rgba(55,55,55,.15)',
      'timeline'    => '#d5d8e1',

      // Alphas
      'alphawhite'          => '255,255,255',
      'alphagrey'           => '55,55,55',
      'alphablue'           => '71,87,120',
      'alphablack'          => '0,0,0',

      // Base Greys
      'lightgreyborder'     => '#C7CCD9',
      'greyborder'          => '#A1A6B0',
      'darkgreyborder'      => '#676A70',
      'lightgreytext'       => '#92969D',
      'greytext'            => '#74777D',
      'darkgreytext'        => '#4B4D51',
      'lightgreybackground' => '#F7F7F7',
      'greybackground'      => '#EBECEE',
      'darkgreybackground'  => '#DFE0E2',

      // Base Blues
      'thinblueborder'      => '#DDE8EF',
      'lightblueborder'     => '#BFCFDA',
      'blueborder'          => '#8C98B8',
      'darkblueborder'      => '#626E82',
      'lightbluebackground' => '#F8F9FC',
      'bluebackground'      => '#ECEEF4',
      'lightbluetext'       => '#8C98B8',
      'bluetext'            => '#6B748C',
      'darkbluetext'        => '#464C5C',

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
      'new-background' => '#eaffea',
      'new-bright' => '#a6f3a6',
      'old-background' => '#ffecec',
      'old-bright' => '#f8cbcb',
      'move-background' => '#fdf5d4',
      'copy-background' => '#f1c40f',

      // Background color for "most" themes.
      'page.background' => '#f3f5f7',
      'page.sidenav' => '#eaedf1',

      'menu.profile.text' => 'rgba(255,255,255,.8)',
      'menu.profile.text.selected' => 'rgba(255,255,255,1)',
      'menu.profile.icon.disabled' => 'rgba(255,255,255,.4)',

      'menu.main.height' => '44px',
      'menu.profile.width' => '240px',

    );
  }

}
