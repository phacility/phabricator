<?php

final class PhabricatorFlagListView extends AphrontView {

  private $flags;
  private $flush = false;

  public function setFlags(array $flags) {
    assert_instances_of($flags, 'PhabricatorFlag');
    $this->flags = $flags;
    return $this;
  }

  public function setFlush($flush) {
    $this->flush = $flush;
    return $this;
  }

  public function render() {
    $user = $this->user;

    require_celerity_resource('phabricator-flag-css');

    $list = new PhabricatorObjectItemListView();
    $list->setCards(true);
    $list->setNoDataString(pht('No flags.'));
    $list->setFlush($this->flush);

    $rows = array();
    foreach ($this->flags as $flag) {
      $class = PhabricatorFlagColor::getCSSClass($flag->getColor());

      $flag_icon = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-flag-icon '.$class,
        ),
        '');

      $edit_link = javelin_tag(
        'a',
        array(
          'href' => '/flag/edit/'.$flag->getObjectPHID().'/',
          'sigil' => 'workflow',
        ),
        pht('Edit'));

      $remove_link = javelin_tag(
        'a',
        array(
          'href' => '/flag/delete/'.$flag->getID().'/',
          'sigil' => 'workflow',
        ),
        pht('Remove'));

      $item = new PhabricatorObjectItemView();
      $item->addIcon('edit', $edit_link);
      $item->addIcon('delete', $remove_link);

      $item->setHeader(hsprintf('%s %s',
        $flag_icon, $flag->getHandle()->renderLink()));

      $item->addAttribute(phabricator_datetime($flag->getDateCreated(), $user));

      if ($flag->getNote()) {
        $item->addAttribute($flag->getNote());
      }

      $list->addItem($item);
    }

    return $list;
  }
}
