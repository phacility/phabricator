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

    $object = $xscript->getObject();

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
        $this->buildTransactionsTranscriptPanel(
          $object,
          $xscript),
        $this->buildProfilerTranscriptPanel($xscript),
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

      $rule_monogram = pht('H%d', $rule_id);
      $rule_uri = '/'.$rule_monogram;

      $rule_item = id(new PHUIObjectItemView())
        ->setObjectName($rule_monogram)
        ->setHeader($rule_xscript->getRuleName())
        ->setHref($rule_uri);

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
        if ($cond_xscript->isForbidden()) {
          $icon = 'fa-ban';
          $color = 'indigo';
          $result = pht('Forbidden');
        } else if ($cond_xscript->getResult()) {
          $icon = 'fa-check';
          $color = 'green';
          $result = pht('Passed');
        } else {
          $icon = 'fa-times';
          $color = 'red';
          $result = pht('Failed');
        }

        if ($cond_xscript->getNote()) {
          $note_text = $cond_xscript->getNote();
          if ($cond_xscript->isForbidden()) {
            $note_text = HeraldStateReasons::getExplanation($note_text);
          }

          $note = phutil_tag(
            'div',
            array(
              'class' => 'herald-condition-note',
            ),
            $note_text);
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

      if ($rule_xscript->isForbidden()) {
        $last_icon = 'fa-ban';
        $last_color = 'indigo';
        $last_result = pht('Forbidden');
        $last_note = pht('Object state prevented rule evaluation.');
      } else if ($rule_xscript->getResult()) {
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
    $viewer = $this->getViewer();
    $adapter = $this->getAdapter();

    $field_names = $adapter->getFieldNameMap();

    $object_xscript = $xscript->getObjectTranscript();

    $rows = array();
    if ($object_xscript) {
      $phid = $object_xscript->getPHID();
      $handles = $this->handles;

      $rows[] = array(
        pht('Object Name'),
        $object_xscript->getName(),
      );

      $rows[] = array(
        pht('Object Type'),
        $object_xscript->getType(),
      );

      $rows[] = array(
        pht('Object PHID'),
        $phid,
      );

      $rows[] = array(
        pht('Object Link'),
        $handles[$phid]->renderLink(),
      );
    }

    foreach ($xscript->getMetadataMap() as $key => $value) {
      $rows[] = array(
        $key,
        $value,
      );
    }

    if ($object_xscript) {
      foreach ($object_xscript->getFields() as $field_type => $value) {
        if (isset($field_names[$field_type])) {
          $field_name = pht('Field: %s', $field_names[$field_type]);
        } else {
          $field_name = pht('Unknown Field ("%s")', $field_type);
        }

        $field_value = $adapter->renderFieldTranscriptValue(
          $viewer,
          $field_type,
          $value);

        $rows[] = array(
          $field_name,
          $field_value,
        );
      }
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

  private function buildTransactionsTranscriptPanel(
    $object,
    HeraldTranscript $xscript) {
    $viewer = $this->getViewer();

    $object_xscript = $xscript->getObjectTranscript();

    $xaction_phids = $object_xscript->getAppliedTransactionPHIDs();

    // If the value is "null", this is an older transcript or this adapter
    // does not use transactions. We render nothing.
    //
    // If the value is "array()", this is a modern transcript which uses
    // transactions, there just weren't any applied. Below, we'll render a
    // "No Transactions Applied" state.
    if ($xaction_phids === null) {
      return null;
    }

    // If this object doesn't implement the right interface, we won't be
    // able to load the transactions. Just bail.
    if (!($object instanceof PhabricatorApplicationTransactionInterface)) {
      return null;
    }

    $query = PhabricatorApplicationTransactionQuery::newQueryForObject(
      $object);

    if ($xaction_phids) {
      $xactions = $query
        ->setViewer($viewer)
        ->withPHIDs($xaction_phids)
        ->execute();
      $xactions = mpull($xactions, null, 'getPHID');
    } else {
      $xactions = array();
    }

    $rows = array();
    foreach ($xaction_phids as $xaction_phid) {
      $xaction = idx($xactions, $xaction_phid);

      $xaction_identifier = $xaction_phid;
      $xaction_date = null;
      $xaction_display = null;
      if ($xaction) {
        $xaction_identifier = $xaction->getID();
        $xaction_date = phabricator_datetime(
          $xaction->getDateCreated(),
          $viewer);

        // Since we don't usually render transactions outside of the context
        // of objects, some of them might depend on missing object data. Out of
        // an abundance of caution, catch any rendering issues.
        try {
          $xaction_display = $xaction->getTitle();
        } catch (Exception $ex) {
          $xaction_display = $ex->getMessage();
        }
      }

      $rows[] = array(
        $xaction_identifier,
        $xaction_display,
        $xaction_date,
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('ID'),
          pht('Transaction'),
          pht('Date'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide',
          null,
        ));

    $box_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Transactions'))
      ->setTable($table_view);

    return $box_view;
  }


  private function buildProfilerTranscriptPanel(HeraldTranscript $xscript) {
    $viewer = $this->getViewer();

    $object_xscript = $xscript->getObjectTranscript();

    $profile = $object_xscript->getProfile();

    // If this is an older transcript without profiler information, don't
    // show anything.
    if ($profile === null) {
      return null;
    }

    $profile = isort($profile, 'elapsed');
    $profile = array_reverse($profile);

    $phids = array();
    foreach ($profile as $frame) {
      if ($frame['type'] === 'rule') {
        $phids[] = $frame['key'];
      }
    }
    $handles = $viewer->loadHandles($phids);

    $field_map = HeraldField::getAllFields();

    $rows = array();
    foreach ($profile as $frame) {
      $cost = $frame['elapsed'];
      $cost = 1000000 * $cost;
      $cost = pht('%sus', new PhutilNumber($cost));

      $type = $frame['type'];
      switch ($type) {
        case 'rule':
          $type_display = pht('Rule');
          break;
        case 'field':
          $type_display = pht('Field');
          break;
        default:
          $type_display = $type;
          break;
      }

      $key = $frame['key'];
      switch ($type) {
        case 'field':
          $field_object = idx($field_map, $key);
          if ($field_object) {
            $key_display = $field_object->getHeraldFieldName();
          } else {
            $key_display = $key;
          }
          break;
        case 'rule':
          $key_display = $handles[$key]->renderLink();
          break;
        default:
          $key_display = $key;
          break;
      }

      $rows[] = array(
        $type_display,
        $key_display,
        $cost,
        pht('%s', new PhutilNumber($frame['count'])),
      );
    }

    $table_view = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Type'),
          pht('What'),
          pht('Cost'),
          pht('Count'),
        ))
      ->setColumnClasses(
        array(
          null,
          'wide',
          'right',
          'right',
        ));

    $box_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Profile'))
      ->setTable($table_view);

    return $box_view;
  }

}
