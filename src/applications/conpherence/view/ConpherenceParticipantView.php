<?php

final class ConpherenceParticipantView extends AphrontView {

  private $conpherence;
  private $updateURI;

  public function setConpherence(ConpherenceThread $conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }

  public function setUpdateURI($uri) {
    $this->updateURI = $uri;
    return $this;
  }

  public function render() {
    $conpherence = $this->conpherence;
    $viewer = $this->getViewer();

    $participants = $conpherence->getParticipants();
    $count = new PhutilNumber(count($participants));
    $handles = $conpherence->getHandles();
    $handles = array_intersect_key($handles, $participants);
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
          ->setIcon('fa-times')
          ->addClass('lightbluetext');
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

    $new_icon = id(new PHUIIconView())
      ->setIcon('fa-plus-square')
      ->setHref($this->updateURI)
      ->setMetadata(array('widget' => null))
      ->addSigil('conpherence-widget-adder');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Participants (%d)', $count))
      ->addClass('widgets-header')
      ->addActionItem($new_icon);

    $content = javelin_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-people',
        'sigil' => 'widgets-people',
      ),
      array(
        $header,
        $body,
      ));

    return $content;
  }

}
