<?php

final class PhabricatorFlagListView extends AphrontView {

  private $flags;
  private $user;

  public function setFlags(array $flags) {
    assert_instances_of($flags, 'PhabricatorFlag');
    $this->flags = $flags;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-flag-css');

    $rows = array();
    foreach ($this->flags as $flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $rows[] = array(
        phutil_render_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$class,
          ),
          ''),
        $flag->getHandle()->renderLink(),
        phutil_escape_html($flag->getNote()),
        phabricator_datetime($flag->getDateCreated(), $user),
        phabricator_render_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/edit/'.$flag->getObjectPHID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_render_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            'Edit Flag')),
        phabricator_render_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/delete/'.$flag->getID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_render_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            'Remove Flag')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        '',
        'Flagged Object',
        'Note',
        'Flagged On',
        '',
        '',
      ));
    $table->setColumnClasses(
      array(
        'narrow',
        'wrap pri',
        'wrap',
        'narrow',
        'narrow action',
        'narrow action',
      ));
    $table->setNoDataString('No flags.');

    return $table->render();
  }
}
