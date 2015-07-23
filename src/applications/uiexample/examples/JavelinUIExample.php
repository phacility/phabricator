<?php

final class JavelinUIExample extends PhabricatorUIExample {

  public function getName() {
    return pht('Javelin UI');
  }

  public function getDescription() {
    return pht('Here are some Javelin UI elements that you could use.');
  }

  public function renderExample() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // toggle-class

    $container_id  = celerity_generate_unique_node_id();
    $button_red_id = celerity_generate_unique_node_id();
    $button_blue_id = celerity_generate_unique_node_id();

    $button_red = javelin_tag(
      'a',
      array(
        'class' => 'button',
        'sigil' => 'jx-toggle-class',
        'href'  => '#',
        'id'    => $button_red_id,
        'meta'  => array(
          'map' => array(
            $container_id => 'jxui-red-border',
            $button_red_id => 'jxui-active',
          ),
        ),
      ),
      pht('Toggle Red Border'));

    $button_blue = javelin_tag(
      'a',
      array(
        'class' => 'button jxui-active',
        'sigil' => 'jx-toggle-class',
        'href'  => '#',
        'id'    => $button_blue_id,
        'meta' => array(
          'state' => true,
          'map' => array(
            $container_id => 'jxui-blue-background',
            $button_blue_id => 'jxui-active',
          ),
        ),
      ),
      pht('Toggle Blue Background'));

    $div = phutil_tag(
      'div',
      array(
        'id' => $container_id,
        'class' => 'jxui-example-container jxui-blue-background',
      ),
      array($button_red, $button_blue));

    return array($div);
  }
}
