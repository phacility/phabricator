<?php

final class PhabricatorFlagListView extends AphrontView {

  private $flags;

  public function setFlags(array $flags) {
    assert_instances_of($flags, 'PhabricatorFlag');
    $this->flags = $flags;
    return $this;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-flag-css');

    $rows = array();
    foreach ($this->flags as $flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $rows[] = array(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-flag-icon '.$class,
          ),
          ''),
        $flag->getHandle()->renderLink(),
        $flag->getNote(),
        phabricator_datetime($flag->getDateCreated(), $user),
        phabricator_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/edit/'.$flag->getObjectPHID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            pht('Edit Flag'))),
        phabricator_form(
          $user,
          array(
            'method' => 'POST',
            'action' => '/flag/delete/'.$flag->getID().'/',
            'sigil'  => 'workflow',
          ),
          phutil_tag(
            'button',
            array(
              'class' => 'small grey',
            ),
            pht('Remove Flag'))),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        '',
        pht('Flagged Object'),
        pht('Note'),
        pht('Flagged On'),
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
    $table->setNoDataString(pht('No flags.'));

    return $table->render();
  }
}
