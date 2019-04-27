<?php

final class DiffusionRepositoryBranchesManagementPanel
  extends DiffusionRepositoryManagementPanel {

  const PANELKEY = 'branches';

  public function getManagementPanelLabel() {
    return pht('Branches');
  }

  public function getManagementPanelOrder() {
    return 1000;
  }

  public function shouldEnableForRepository(
    PhabricatorRepository $repository) {
    return ($repository->isGit() || $repository->isHg());
  }

  public function getManagementPanelIcon() {
    $repository = $this->getRepository();

    $has_any =
      $repository->getDetail('default-branch') ||
      $repository->getTrackOnlyRules() ||
      $repository->getPermanentRefRules();

    if ($has_any) {
      return 'fa-code-fork';
    } else {
      return 'fa-code-fork grey';
    }
  }

  protected function getEditEngineFieldKeys() {
    return array(
      'defaultBranch',
      'fetchRefs',
      'permanentRefs',
      'trackOnly',
    );
  }

  public function buildManagementPanelCurtain() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $action_list = $this->newActionList();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $repository,
      PhabricatorPolicyCapability::CAN_EDIT);

    $branches_uri = $this->getEditPageURI();

    $action_list->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Branches'))
        ->setHref($branches_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $this->newCurtainView()
      ->setActionList($action_list);
  }

  public function buildManagementPanelContent() {
    $repository = $this->getRepository();
    $viewer = $this->getViewer();
    $content = array();

    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $default_branch = nonempty(
      $repository->getDetail('default-branch'),
      phutil_tag('em', array(), $repository->getDefaultBranch()));
    $view->addProperty(pht('Default Branch'), $default_branch);

    if ($repository->supportsFetchRules()) {
      $fetch_only = $repository->getFetchRules();
      if ($fetch_only) {
        $fetch_display = implode(', ', $fetch_only);
      } else {
        $fetch_display = phutil_tag('em', array(), pht('Fetch All Refs'));
      }
      $view->addProperty(pht('Fetch Refs'), $fetch_display);
    }

    $track_only_rules = $repository->getTrackOnlyRules();
    if ($track_only_rules) {
      $track_only_rules = implode(', ', $track_only_rules);
      $view->addProperty(pht('Track Only'), $track_only_rules);
    }

    $publishing_disabled = $repository->isPublishingDisabled();
    if ($publishing_disabled) {
      $permanent_display =
        phutil_tag('em', array(), pht('Publishing Disabled'));
    } else {
      $permanent_rules = $repository->getPermanentRefRules();
      if ($permanent_rules) {
        $permanent_display = implode(', ', $permanent_rules);
      } else {
        $permanent_display = phutil_tag('em', array(), pht('All Branches'));
      }
    }
    $view->addProperty(pht('Permanent Refs'), $permanent_display);

    $content[] = $this->newBox(pht('Branches'), $view);

    if (!$repository->isImporting()) {
      $request = $this->getRequest();
      $pager = id(new PHUIPagerView())
        ->readFromRequest($request);

      $params = array(
        'offset' => $pager->getOffset(),
        'limit' => $pager->getPageSize() + 1,
        'repository' => $repository->getID(),
      );

      $branches = id(new ConduitCall('diffusion.branchquery', $params))
        ->setUser($viewer)
        ->execute();
      $branches = DiffusionRepositoryRef::loadAllFromDictionaries($branches);
      $branches = $pager->sliceResults($branches);
      $can_close_branches = ($repository->isHg());

      $publisher = $repository->newPublisher();

      $rows = array();
      foreach ($branches as $branch) {
        $branch_name = $branch->getShortName();
        $permanent = $publisher->shouldPublishRef($branch);

        $default = $repository->getDefaultBranch();
        $icon = null;
        if ($default == $branch->getShortName()) {
          $icon = id(new PHUIIconView())
            ->setIcon('fa-code-fork');
        }

        $fields = $branch->getRawFields();
        $closed = idx($fields, 'closed');
        if ($closed) {
          $status = pht('Closed');
        } else {
          $status = pht('Open');
        }

        if ($publishing_disabled) {
          $permanent_status = pht('Publishing Disabled');
        } else {
          if ($permanent) {
            $permanent_status = pht('Permanent');
          } else {
            $permanent_status = pht('Not Permanent');
          }
        }

        $rows[] = array(
          $icon,
          $branch_name,
          $status,
          $permanent_status,
        );
      }
      $branch_table = new AphrontTableView($rows);
      $branch_table->setHeaders(
        array(
          '',
          pht('Branch'),
          pht('Status'),
          pht('Permanent'),
        ));
      $branch_table->setColumnClasses(
        array(
          '',
          'pri',
          'narrow',
          'wide',
        ));
      $branch_table->setColumnVisibility(
        array(
          true,
          true,
          $can_close_branches,
          true,
        ));

      $box = $this->newBox(pht('Branch Status'), $branch_table);
      $box->setPager($pager);
      $content[] = $box;
    } else {
      $content[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(
          pht(
            'Branch status is unavailable while the repository is still '.
            'importing.'));
    }

    return $content;
  }

}
