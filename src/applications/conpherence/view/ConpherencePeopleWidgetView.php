<?php
/**
 * @group conpherence
 */
final class ConpherencePeopleWidgetView extends ConpherenceWidgetView {

  public function render() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $user = $this->getUser();
    $conpherence = $this->getConpherence();
    $participants = $conpherence->getParticipants();
    $handles = $conpherence->getHandles();

    // ye olde add people widget
    $add_widget = phabricator_form(
      $user,
      array(
        'method' => 'POST',
        'action' => $this->getUpdateURI(),
        'sigil' => 'add-person',
        'meta' => array(
          'action' => 'add_person'
        )
      ),
      array(
        id(new AphrontFormTokenizerControl())
        ->setPlaceholder(pht('Add a person...'))
        ->setName('add_person')
        ->setUser($user)
        ->setDatasource('/typeahead/common/users/')
        ->setLimit(1),
        phutil_tag(
          'button',
          array(
            'type' => 'submit',
            'class' => 'people-add-button',
          ),
          pht('Add'))
      ));
    $header = phutil_tag(
      'div',
      array(
        'class' => 'people-widget-header'
      ),
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'add-people-widget',
          ),
          $add_widget),
        phutil_tag(
          'div',
          array(
            'class' => 'divider'
          ),
          '')
      ));

    $body = array();
    // future proof by using participants to iterate through handles;
    // we may have non-people handles sooner or later
    foreach ($participants as $user_phid => $participant) {
      $handle = $handles[$user_phid];
      $remove_html = '';
      if ($user_phid == $user->getPHID()) {
        $remove_html = javelin_tag(
          'a',
          array(
            'class' => 'remove',
            'sigil' => 'remove-person',
            'meta' => array(
              'remove_person' => $handle->getPHID(),
              'action' => 'remove_person',
            )
          ),
          phutil_tag(
            'span',
            array(
              'class' => 'icon'
            ),
            'x'));
      }
      $body[] = phutil_tag(
        'div',
        array(
          'class' => 'person-entry'
        ),
        array(
          phutil_tag(
            'a',
            array(
              'class' => 'pic',
            ),
            phutil_tag(
              'img',
              array(
                'src' => $handle->getImageURI()
              ),
              '')),
          $handle->renderLink(),
          $remove_html));
    }

    return array($header, $body);
  }
}
