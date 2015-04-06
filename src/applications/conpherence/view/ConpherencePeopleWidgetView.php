<?php

final class ConpherencePeopleWidgetView extends ConpherenceWidgetView {

  public function render() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $user = $this->getUser();
    $conpherence = $this->getConpherence();
    $participants = $conpherence->getParticipants();
    $handles = $conpherence->getHandles();
    $handles = msort($handles, 'getName');
    $head_handles = array_select_keys($handles, array($user->getPHID()));
    $handles = $head_handles + $handles;

    $body = array();
    foreach ($handles as $user_phid => $handle) {
      $remove_html = '';
      if ($user_phid == $user->getPHID()) {
        $icon = id(new PHUIIconView())
          ->setIconFont('fa-times lightbluetext');
        $remove_html = javelin_tag(
          'a',
          array(
            'class' => 'remove',
            'sigil' => 'remove-person',
            'meta' => array(
              'remove_person' => $handle->getPHID(),
              'action' => 'remove_person',
            ),
          ),
          $icon);
      }
      $body[] = phutil_tag(
        'div',
        array(
          'class' => 'person-entry grouped',
        ),
        array(
          phutil_tag(
            'a',
            array(
              'class' => 'pic',
              'href' => $handle->getURI(),
            ),
            phutil_tag(
              'img',
              array(
                'src' => $handle->getImageURI(),
              ),
              '')),
          $handle->renderLink(),
          $remove_html,
        ));
    }

    return $body;
  }

}
