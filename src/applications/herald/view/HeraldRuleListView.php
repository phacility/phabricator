<?php

final class HeraldRuleListView extends AphrontView {

  private $rules;
  private $handles;

  private $showAuthor;
  private $showRuleType;

  public function setRules(array $rules) {
    assert_instances_of($rules, 'HeraldRule');
    $this->rules = $rules;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setShowAuthor($show_author) {
    $this->showAuthor = $show_author;
    return $this;
  }

  public function setShowRuleType($show_rule_type) {
    $this->showRuleType = $show_rule_type;
    return $this;
  }

  public function render() {

    $type_map = HeraldRuleTypeConfig::getRuleTypeMap();

    $list = new PhabricatorObjectItemListView();
    $list->setFlush(true);
    $list->setCards(true);
    foreach ($this->rules as $rule) {

      if ($rule->getRuleType() == HeraldRuleTypeConfig::RULE_TYPE_GLOBAL) {
        $author = pht('Global Rule');
      } else {
        $author = $this->handles[$rule->getAuthorPHID()]->renderLink();
        $author = pht('Editor: %s', $author);
      }

      $edit_log = phutil_tag(
        'a',
        array(
          'href' => '/herald/history/'.$rule->getID().'/',
        ),
        pht('View Edit Log'));

      $delete = javelin_tag(
        'a',
        array(
          'href' => '/herald/delete/'.$rule->getID().'/',
          'sigil' => 'workflow',
        ),
        pht('Delete'));

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName($type_map[$rule->getRuleType()])
        ->setHeader($rule->getName())
        ->setHref('/herald/rule/'.$rule->getID().'/')
        ->addAttribute($edit_log)
        ->addIcon('none', $author)
        ->addAction(
          id(new PHUIListItemView())
            ->setHref('/herald/delete/'.$rule->getID().'/')
            ->setIcon('delete')
            ->setWorkflow(true));

      $list->addItem($item);
    }

    $list->setNoDataString(pht("No matching rules."));

    return $list;
  }
}
