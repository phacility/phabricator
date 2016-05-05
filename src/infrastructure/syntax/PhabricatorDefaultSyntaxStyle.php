<?php

final class PhabricatorDefaultSyntaxStyle
  extends PhabricatorSyntaxStyle {

  const STYLEKEY = 'default';

  public function getStyleName() {
    return pht('Default');
  }

  public function getStyleMap() {
    return array(
      'hll' => 'color: #ffffcc',
      'c' => 'color: #74777d',
      'cm' => 'color: #74777d',
      'c1' => 'color: #74777d',
      'cs' => 'color: #74777d',
      'sd' => 'color: #000000',
      'sh' => 'color: #000000',
      's' => 'color: #766510',
      'sb' => 'color: #766510',
      'sc' => 'color: #766510',
      's2' => 'color: #766510',
      's1' => 'color: #766510',
      'sx' => 'color: #766510',
      'sr' => 'color: #bb6688',
      'nv' => 'color: #001294',
      'vi' => 'color: #001294',
      'vg' => 'color: #001294',
      'na' => 'color: #354bb3',
      'kc' => 'color: #000a65',
      'no' => 'color: #000a65',
      'k' => 'color: #aa4000',
      'kd' => 'color: #aa4000',
      'kn' => 'color: #aa4000',
      'kt' => 'color: #aa4000',
      'cp' => 'color: #304a96',
      'kp' => 'color: #304a96',
      'kr' => 'color: #304a96',
      'nb' => 'color: #304a96',
      'bp' => 'color: #304a96',
      'nc' => 'color: #00702a',
      'nt' => 'color: #00702a',
      'vc' => 'color: #00702a',
      'nf' => 'color: #004012',
      'nx' => 'color: #004012',
      'o' => 'color: #aa2211',
      'ss' => 'color: #aa2211',
      'm' => 'color: #601200',
      'mf' => 'color: #601200',
      'mh' => 'color: #601200',
      'mi' => 'color: #601200',
      'mo' => 'color: #601200',
      'il' => 'color: #601200',
      'gd' => 'color: #a00000',
      'gr' => 'color: #ff0000',
      'gh' => 'color: #000080',
      'gi' => 'color: #00a000',
      'go' => 'color: #808080',
      'gp' => 'color: #000080',
      'gu' => 'color: #800080',
      'gt' => 'color: #0040d0',
      'nd' => 'color: #aa22ff',
      'ni' => 'color: #92969d',
      'ne' => 'color: #d2413a',
      'nl' => 'color: #a0a000',
      'nn' => 'color: #0000ff',
      'ow' => 'color: #aa22ff',
      'w' => 'color: #bbbbbb',
      'se' => 'color: #bb6622',
      'si' => 'color: #bb66bb',
    );
  }

}
