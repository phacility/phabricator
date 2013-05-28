<?php

final class HeraldRuleEditHistoryView extends AphrontView {

  private $edits;
  private $handles;

  public function setEdits(array $edits) {
    $this->edits = $edits;
    return $this;
  }

  public function getEdits() {
    return $this->edits;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $list = new PhabricatorObjectItemListView();
    $list->setFlush(true);
    $list->setCards(true);

    foreach ($this->edits as $edit) {
      $name = nonempty($edit->getRuleName(), 'Unknown Rule');
      $rule_name = phutil_tag(
        'strong',
        array(),
        $name);

      switch ($edit->getAction()) {
        case 'create':
          $details = pht("Created rule '%s'.", $rule_name);
          break;
        case 'delete':
          $details = pht("Deleted rule '%s'.", $rule_name);
          break;
        case 'edit':
        default:
          $details = pht("Edited rule '%s'.", $rule_name);
          break;
      }

      $editor = $this->handles[$edit->getEditorPHID()]->renderLink();
      $date = phabricator_datetime($edit->getDateCreated(), $this->user);

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName(pht('Rule %d', $edit->getRuleID()))
        ->setSubHead($details)
        ->addIcon('none', $date)
        ->addByLine(pht('Editor: %s', $editor));

      $list->addItem($item);
    }

    $list->setNoDataString(pht('No edits for rule.'));

    return $list;
  }
}
