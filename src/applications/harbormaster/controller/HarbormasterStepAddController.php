<?php

final class HarbormasterStepAddController
  extends HarbormasterPlanController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $plan = id(new HarbormasterBuildPlanQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$plan) {
      return new Aphront404Response();
    }

    $plan_id = $plan->getID();
    $cancel_uri = $this->getApplicationURI("plan/{$plan_id}/");
    $plan_title = pht('Plan %d', $plan_id);

    $all = HarbormasterBuildStepImplementation::getImplementations();
    $all = msort($all, 'getName');

    $all_groups = HarbormasterBuildStepGroup::getAllGroups();
    foreach ($all as $impl) {
      $group_key = $impl->getBuildStepGroupKey();
      if (empty($all_groups[$group_key])) {
        throw new Exception(
          pht(
            'Build step "%s" has step group key "%s", but no step group '.
            'with that key exists.',
            get_class($impl),
            $group_key));
      }
    }

    $groups = mgroup($all, 'getBuildStepGroupKey');
    $boxes = array();

    $enabled_groups = HarbormasterBuildStepGroup::getAllEnabledGroups();
    foreach ($enabled_groups as $group) {
      $list = id(new PHUIObjectItemListView())
        ->setNoDataString(
          pht('This group has no available build steps.'));

      $steps = idx($groups, $group->getGroupKey(), array());

      foreach ($steps as $key => $impl) {
        if ($impl->shouldRequireAutotargeting()) {
          unset($steps[$key]);
          continue;
        }
      }

      if (!$steps && !$group->shouldShowIfEmpty()) {
        continue;
      }

      foreach ($steps as $key => $impl) {
        $class = get_class($impl);

        $new_uri = $this->getApplicationURI("step/new/{$plan_id}/{$class}/");

        $item = id(new PHUIObjectItemView())
          ->setHeader($impl->getName())
          ->setHref($new_uri)
          ->addAttribute($impl->getGenericDescription());

        $list->addItem($item);
      }

      $box = id(new PHUIObjectBoxView())
        ->setHeaderText($group->getGroupName())
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->appendChild($list);

      $boxes[] = $box;
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($plan_title, $cancel_uri)
      ->addTextCrumb(pht('Add Build Step'))
      ->setBorder(true);

    $title = array($plan_title, pht('Add Build Step'));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Add Build Step'))
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $boxes,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
