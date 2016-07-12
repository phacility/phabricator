<?php

final class ConpherencePeopleWidgetView extends ConpherenceWidgetView {

  public function render() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $viewer = $this->getUser();

    $participants = $conpherence->getParticipants();
    $handles = $conpherence->getHandles();
    $head_handles = array_select_keys($handles, array($viewer->getPHID()));
    $handle_list = mpull($handles, 'getName');
    natcasesort($handle_list);
    $handles = mpull($handles, null, 'getName');
    $handles = array_select_keys($handles, $handle_list);
    $head_handles = mpull($head_handles, null, 'getName');
    $handles = $head_handles + $handles;

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $conpherence,
      PhabricatorPolicyCapability::CAN_EDIT);

    $body = array();
    foreach ($handles as $handle) {
      $user_phid = $handle->getPHID();

      if (($user_phid == $viewer->getPHID()) || $can_edit) {
        $icon = id(new PHUIIconView())
          ->setIcon('fa-times lightbluetext');
        $remove_html = javelin_tag(
          'a',
          array(
            'class' => 'remove',
            'sigil' => 'remove-person',
            'meta' => array(
              'remove_person' => $user_phid,
              'action' => 'remove_person',
            ),
          ),
          $icon);
      } else {
        $remove_html = null;
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
