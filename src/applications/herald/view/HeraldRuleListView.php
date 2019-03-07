<?php

final class HeraldRuleListView
  extends AphrontView {

  private $rules;

  public function setRules(array $rules) {
    assert_instances_of($rules, 'HeraldRule');
    $this->rules = $rules;
    return $this;
  }

  public function render() {
    return $this->newObjectList();
  }

  public function newObjectList() {
    $viewer = $this->getViewer();
    $rules = $this->rules;

    $handles = $viewer->loadHandles(mpull($rules, 'getAuthorPHID'));

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setViewer($viewer);
    foreach ($rules as $rule) {
      $monogram = $rule->getMonogram();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($monogram)
        ->setHeader($rule->getName())
        ->setHref($rule->getURI());

      if ($rule->isPersonalRule()) {
        $item->addIcon('fa-user', pht('Personal Rule'));
        $item->addByline(
          pht(
            'Authored by %s',
            $handles[$rule->getAuthorPHID()]->renderLink()));
      } else if ($rule->isObjectRule()) {
        $item->addIcon('fa-briefcase', pht('Object Rule'));
      } else {
        $item->addIcon('fa-globe', pht('Global Rule'));
      }

      if ($rule->getIsDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-lock grey', pht('Disabled'));
      } else if (!$rule->hasValidAuthor()) {
        $item->setDisabled(true);
        $item->addIcon('fa-user grey', pht('Author Not Active'));
      }

      $content_type_name = idx($content_type_map, $rule->getContentType());
      $item->addAttribute(pht('Affects: %s', $content_type_name));

      $list->addItem($item);
    }

    return $list;
  }

}
