<?php

final class HeraldTranscriptController extends HeraldController {

  private $handles;
  private $adapter;

  private function getAdapter() {
    return $this->adapter;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $xscript = id(new HeraldTranscriptQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$xscript) {
      return new Aphront404Response();
    }

    require_celerity_resource('herald-test-css');
    $content = array();

    $object_xscript = $xscript->getObjectTranscript();
    if (!$object_xscript) {
      $notice = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->setTitle(pht('Old Transcript'))
        ->appendChild(phutil_tag(
          'p',
          array(),
          pht('Details of this transcript have been garbage collected.')));
      $content[] = $notice;
    } else {
      $map = HeraldAdapter::getEnabledAdapterMap($viewer);
      $object_type = $object_xscript->getType();
      if (empty($map[$object_type])) {
        // TODO: We should filter these out in the Query, but we have to load
        // the objectTranscript right now, which is potentially enormous. We
        // should denormalize the object type, or move the data into a separate
        // table, and then filter this earlier (and thus raise a better error).
        // For now, just block access so we don't violate policies.
        throw new Exception(
          pht('This transcript has an invalid or inaccessible adapter.'));
      }

      $this->adapter = HeraldAdapter::getAdapterForContentType($object_type);

      $phids = $this->getTranscriptPHIDs($xscript);
      $phids = array_unique($phids);
      $phids = array_filter($phids);

      $handles = $this->loadViewerHandles($phids);
      $this->handles = $handles;

      if ($xscript->getDryRun()) {
        $notice = new PHUIInfoView();
        $notice->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Dry Run'));
        $notice->appendChild(
          pht(
            'This was a dry run to test Herald rules, '.
            'no actions were executed.'));
        $content[] = $notice;
      }

      $warning_panel = $this->buildWarningPanel($xscript);
      $content[] = $warning_panel;

      $content[] = array(
        $this->buildActionTranscriptPanel($xscript),
        $this->buildObjectTranscriptPanel($xscript),
      );
    }

    $crumbs = id($this->buildApplicationCrumbs())
      ->addTextCrumb(
        pht('Transcripts'),
        $this->getApplicationURI('/transcript/'))
      ->addTextCrumb($xscript->getID())
      ->setBorder(true);

    $title = pht('Transcript: %s', $xscript->getID());

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-file');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  protected function renderConditionTestValue($condition, $handles) {
    // TODO: This is all a hacky mess and should be driven through FieldValue
    // eventually.

    switch ($condition->getFieldName()) {
      case HeraldAnotherRuleField::FIELDCONST:
        $value = array($condition->getTestValue());
        break;
      default:
        $value = $condition->getTestValue();
        break;
    }

    if (!is_scalar($value) && $value !== null) {
      foreach ($value as $key => $phid) {
        $handle = idx($handles, $phid);
        if ($handle && $handle->isComplete()) {
          $value[$key] = $handle->getName();
        } else {
          // This happens for things like task priorities, statuses, and
          // custom fields.
          $value[$key] = $phid;
        }
      }
      sort($value);
      $value = implode(', ', $value);
    }

    return phutil_tag('span', array('class' => 'condition-test-value'), $value);
  }

  protected function getTranscriptPHIDs($xscript) {
    $phids = array();

    $object_xscript = $xscript->getObjectTranscript();
    if (!$object_xscript) {
      return array();
    }

    $phids[] = $object_xscript->getPHID();

    foreach ($xscript->getApplyTranscripts() as $apply_xscript) {
      // TODO: This is total hacks. Add another amazing layer of abstraction.
      $target = (array)$apply_xscript->getTarget();
      foreach ($target as $phid) {
        if ($phid) {
          $phids[] = $phid;
        }
      }
    }

    foreach ($xscript->getRuleTranscripts() as $rule_xscript) {
      $phids[] = $rule_xscript->getRuleOwner();
    }

    $condition_xscripts = $xscript->getConditionTranscripts();
    if ($condition_xscripts) {
      $condition_xscripts = call_user_func_array(
        'array_merge',
        $condition_xscripts);
    }
    foreach ($condition_xscripts as $condition_xscript) {
      switch ($condition_xscript->getFieldName()) {
        case HeraldAnotherRuleField::FIELDCONST:
          $phids[] = $condition_xscript->getTestValue();
          break;
        default:
          $value = $condition_xscript->getTestValue();
          // TODO: Also total hacks.
          if (is_array($value)) {
            foreach ($value as $phid) {
              if ($phid) {
                // TODO: Probably need to make sure this
                // "looks like" a PHID or decrease the level of hacks here;
                // this used to be an is_numeric() check in Facebook land.
                $phids[] = $phid;
              }
            }
          }
          break;
      }
    }

    return $phids;
  }

  private function buildWarningPanel(HeraldTranscript $xscript) {
    $request = $this->getRequest();
    $panel = null;
    if ($xscript->getObjectTranscript()) {
      $handles = $this->handles;
      $object_xscript = $xscript->getObjectTranscript();
      $handle = $handles[$object_xscript->getPHID()];
      if ($handle->getType() ==
          PhabricatorRepositoryCommitPHIDType::TYPECONST) {
        $commit = id(new DiffusionCommitQuery())
          ->setViewer($request->getUser())
          ->withPHIDs(array($handle->getPHID()))
          ->executeOne();
        if ($commit) {
          $repository = $commit->getRepository();
          if ($repository->isImporting()) {
            $title = pht(
              'The %s repository is still importing.',
              $repository->getMonogram());
            $body = pht(
              'Herald rules will not trigger until import completes.');
          } else if (!$repository->isTracked()) {
            $title = pht(
              'The %s repository is not tracked.',
              $repository->getMonogram());
            $body = pht(
              'Herald rules will not trigger until tracking is enabled.');
          } else {
            return $panel;
          }
          $panel = id(new PHUIInfoView())
            ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
            ->setTitle($title)
            ->appendChild($body);
        }
      }
    }
    return $panel;
  }

  private function buildActionTranscriptPanel(HeraldTranscript $xscript) {
    $action_xscript = mgroup($xscript->getApplyTranscripts(), 'getRuleID');

    $adapter = $this->getAdapter();

    $field_names = $adapter->getFieldNameMap();
    $condition_names = $adapter->getConditionNameMap();

    $handles = $this->handles;

    $action_map = $xscript->getApplyTranscripts();
    $action_map = mgroup($action_map, 'getRuleID');

    $rule_list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('No Herald rules applied to this object.'))
      ->setFlush(true);

    $rule_xscripts = $xscript->getRuleTranscripts();
    $rule_xscripts = msort($rule_xscripts, 'getRuleID');
    foreach ($rule_xscripts as $rule_xscript) {
      $rule_id = $rule_xscript->getRuleID();

      $rule_item = id(new PHUIObjectItemView())
        ->setObjectName(pht('H%d', $rule_id))
        ->setHeader($rule_xscript->getRuleName());

      if (!$rule_xscript->getResult()) {
        $rule_item->setDisabled(true);
      }

      $rule_list->addItem($rule_item);

      // Build the field/condition transcript.

      $cond_xscripts = $xscript->getConditionTranscriptsForRule($rule_id);

      $cond_list = id(new PHUIStatusListView());
      $cond_list->addItem(
        id(new PHUIStatusItemView())
          ->setTarget(phutil_tag('strong', array(), pht('Conditions'))));

      foreach ($cond_xscripts as $cond_xscript) {
        if ($cond_xscript->getResult()) {
          $icon = 'fa-check';
          $color = 'green';
          $result = pht('Passed');
        } else {
          $icon = 'fa-times';
          $color = 'red';
          $result = pht('Failed');
        }

        if ($cond_xscript->getNote()) {
          $note = phutil_tag(
            'div',
            array(
              'class' => 'herald-condition-note',
            ),
            $cond_xscript->getNote());
        } else {
          $note = null;
        }

        // TODO: This is not really translatable and should be driven through
        // HeraldField.
        $explanation = pht(
          '%s %s %s',
          idx($field_names, $cond_xscript->getFieldName(), pht('Unknown')),
          idx($condition_names, $cond_xscript->getCondition(), pht('Unknown')),
          $this->renderConditionTestValue($cond_xscript, $handles));

        $cond_item = id(new PHUIStatusItemView())
          ->setIcon($icon, $color)
          ->setTarget($result)
          ->setNote(array($explanation, $note));

        $cond_list->addItem($cond_item);
      }

      if ($rule_xscript->getResult()) {
        $last_icon = 'fa-check-circle';
        $last_color = 'green';
        $last_result = pht('Passed');
        $last_note = pht('Rule passed.');
      } else {
        $last_icon = 'fa-times-circle';
        $last_color = 'red';
        $last_result = pht('Failed');
        $last_note = pht('Rule failed.');
      }

      $cond_last = id(new PHUIStatusItemView())
        ->setIcon($last_icon, $last_color)
        ->setTarget(phutil_tag('strong', array(), $last_result))
        ->setNote($last_note);
      $cond_list->addItem($cond_last);

      $cond_box = id(new PHUIBoxView())
        ->appendChild($cond_list)
        ->addMargin(PHUI::MARGIN_LARGE_LEFT);

      $rule_item->appendChild($cond_box);

      if (!$rule_xscript->getResult()) {
        // If the rule didn't pass, don't generate an action transcript since
        // actions didn't apply.
        continue;
      }

      $cond_box->addMargin(PHUI::MARGIN_MEDIUM_BOTTOM);

      $action_xscripts = idx($action_map, $rule_id, array());
      foreach ($action_xscripts as $action_xscript) {
        $action_key = $action_xscript->getAction();
        $action = $adapter->getActionImplementation($action_key);

        if ($action) {
          $name = $action->getHeraldActionName();
          $action->setViewer($this->getViewer());
        } else {
          $name = pht('Unknown Action ("%s")', $action_key);
        }

        $name = pht('Action: %s', $name);

        $action_list = id(new PHUIStatusListView());
        $action_list->addItem(
          id(new PHUIStatusItemView())
            ->setTarget(phutil_tag('strong', array(), $name)));

        $action_box = id(new PHUIBoxView())
          ->appendChild($action_list)
          ->addMargin(PHUI::MARGIN_LARGE_LEFT);

        $rule_item->appendChild($action_box);

        $log = $action_xscript->getAppliedReason();

        // Handle older transcripts which used a static string to record
        // action results.

        if ($xscript->getDryRun()) {
          $action_list->addItem(
            id(new PHUIStatusItemView())
              ->setIcon('fa-ban', 'grey')
              ->setTarget(pht('Dry Run'))
              ->setNote(
                pht(
                  'This was a dry run, so no actions were taken.')));
          continue;
        } else if (!is_array($log)) {
          $action_list->addItem(
            id(new PHUIStatusItemView())
              ->setIcon('fa-clock-o', 'grey')
              ->setTarget(pht('Old Transcript'))
              ->setNote(
                pht(
                  'This is an old transcript which uses an obsolete log '.
                  'format. Detailed action information is not available.')));
          continue;
        }

        foreach ($log as $entry) {
          $type = idx($entry, 'type');
          $data = idx($entry, 'data');

          if ($action) {
            $icon = $action->renderActionEffectIcon($type, $data);
            $color = $action->renderActionEffectColor($type, $data);
            $name = $action->renderActionEffectName($type, $data);
            $note = $action->renderEffectDescription($type, $data);
          } else {
            $icon = 'fa-question-circle';
            $color = 'indigo';
            $name = pht('Unknown Effect ("%s")', $type);
            $note = null;
          }

          $action_item = id(new PHUIStatusItemView())
            ->setIcon($icon, $color)
            ->setTarget($name)
            ->setNote($note);

          $action_list->addItem($action_item);
        }
      }
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Rule Transcript'))
      ->appendChild($rule_list);

    return $box;
  }

  private function buildObjectTranscriptPanel(HeraldTranscript $xscript) {

    $adapter = $this->getAdapter();
    $field_names = $adapter->getFieldNameMap();

    $object_xscript = $xscript->getObjectTranscript();

    $data = array();
    if ($object_xscript) {
      $phid = $object_xscript->getPHID();
      $handles = $this->handles;

      $data += array(
        pht('Object Name') => $object_xscript->getName(),
        pht('Object Type') => $object_xscript->getType(),
        pht('Object PHID') => $phid,
        pht('Object Link') => $handles[$phid]->renderLink(),
      );
    }

    $data += $xscript->getMetadataMap();

    if ($object_xscript) {
      foreach ($object_xscript->getFields() as $field => $value) {
        $field = idx($field_names, $field, '['.$field.'?]');
        $data['Field: '.$field] = $value;
      }
    }

    $rows = array();
    foreach ($data as $name => $value) {
      if (!($value instanceof PhutilSafeHTML)) {
        if (!is_scalar($value) && !is_null($value)) {
          $value = implode("\n", $value);
        }

        if (strlen($value) > 256) {
          $value = phutil_tag(
            'textarea',
            array(
              'class' => 'herald-field-value-transcript',
            ),
            $value);
        }
      }

      $rows[] = array($name, $value);
    }

    $property_list = new PHUIPropertyListView();
    $property_list->setStacked(true);
    foreach ($rows as $row) {
      $property_list->addProperty($row[0], $row[1]);
    }

    $box = new PHUIObjectBoxView();
    $box->setHeaderText(pht('Object Transcript'));
    $box->appendChild($property_list);

    return $box;
  }


}
