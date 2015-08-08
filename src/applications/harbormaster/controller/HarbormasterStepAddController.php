<?php

final class HarbormasterStepAddController extends HarbormasterController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $this->requireApplicationCapability(
      HarbormasterManagePlansCapability::CAPABILITY);

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
    $lists = array();

    $enabled_groups = HarbormasterBuildStepGroup::getAllEnabledGroups();
    foreach ($enabled_groups as $group) {
      $list = id(new PHUIObjectItemListView())
        ->setHeader($group->getGroupName())
        ->setNoDataString(
          pht(
            'This group has no available build steps.'));

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

      $lists[] = $list;
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($plan_title, $cancel_uri)
      ->addTextCrumb(pht('Add Build Step'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Add Build Step'))
      ->appendChild($lists);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => array(
          $plan_title,
          pht('Add Build Step'),
        ),
      ));
  }

}
