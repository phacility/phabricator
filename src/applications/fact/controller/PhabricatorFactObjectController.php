<?php

final class PhabricatorFactObjectController
  extends PhabricatorFactController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $phid = $request->getURIData('phid');
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withNames(array($phid))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    $engines = PhabricatorFactEngine::loadAllEngines();
    foreach ($engines as $key => $engine) {
      $engine = id(clone $engine)
        ->setViewer($viewer);
      $engines[$key] = $engine;

      if (!$engine->supportsDatapointsForObject($object)) {
        unset($engines[$key]);
      }
    }

    if (!$engines) {
      return $this->newDialog()
        ->setTitle(pht('No Engines'))
        ->appendParagraph(
          pht(
            'No fact engines support generating facts for this object.'))
        ->addCancelButton($this->getApplicationURI());
    }

    $key_dimension = new PhabricatorFactKeyDimension();
    $object_phid = $object->getPHID();

    $facts = array();
    $generated_datapoints = array();
    $timings = array();
    foreach ($engines as $key => $engine) {
      $engine_facts = $engine->newFacts();
      $engine_facts = mpull($engine_facts, null, 'getKey');
      $facts[$key] = $engine_facts;

      $t_start = microtime(true);
      $generated_datapoints[$key] = $engine->newDatapointsForObject($object);
      $t_end = microtime(true);

      $timings[$key] = ($t_end - $t_start);
    }

    $object_id = id(new PhabricatorFactObjectDimension())
      ->newDimensionID($object_phid, true);

    $stored_datapoints = id(new PhabricatorFactDatapointQuery())
      ->withFacts(array_mergev($facts))
      ->withObjectPHIDs(array($object_phid))
      ->needVectors(true)
      ->execute();

    $stored_groups = array();
    foreach ($stored_datapoints as $stored_datapoint) {
      $stored_groups[$stored_datapoint['key']][] = $stored_datapoint;
    }

    $stored_map = array();
    foreach ($engines as $key => $engine) {
      $stored_map[$key] = array();
      foreach ($facts[$key] as $fact) {
        $fact_datapoints = idx($stored_groups, $fact->getKey(), array());
        $fact_datapoints = igroup($fact_datapoints, 'vector');
        $stored_map[$key] += $fact_datapoints;
      }
    }

    $handle_phids = array();
    $handle_phids[] = $object->getPHID();
    foreach ($generated_datapoints as $key => $datapoint_set) {
      foreach ($datapoint_set as $datapoint) {
        $dimension_phid = $datapoint->getDimensionPHID();
        if ($dimension_phid !== null) {
          $handle_phids[$dimension_phid] = $dimension_phid;
        }
      }
    }

    foreach ($stored_map as $key => $stored_datapoints) {
      foreach ($stored_datapoints as $vector_key => $datapoints) {
        foreach ($datapoints as $datapoint) {
          $dimension_phid = $datapoint['dimensionPHID'];
          if ($dimension_phid !== null) {
            $handle_phids[$dimension_phid] = $dimension_phid;
          }
        }
      }
    }

    $handles = $viewer->loadHandles($handle_phids);

    $dimension_map = id(new PhabricatorFactObjectDimension())
      ->newDimensionMap($handle_phids, true);

    $content = array();

    $object_list = id(new PHUIPropertyListView())
      ->setViewer($viewer)
      ->addProperty(
        pht('Object'),
        $handles[$object->getPHID()]->renderLink());

    $total_cost = array_sum($timings);
    $total_cost = pht('%sms', new PhutilNumber((int)(1000 * $total_cost)));
    $object_list->addProperty(pht('Total Cost'), $total_cost);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Fact Extraction Report'))
      ->addPropertyList($object_list);

    $content[] = $object_box;

    $icon_fact = id(new PHUIIconView())
      ->setIcon('fa-line-chart green')
      ->setTooltip(pht('Consistent Fact'));

    $icon_nodata = id(new PHUIIconView())
      ->setIcon('fa-question-circle-o violet')
      ->setTooltip(pht('No Stored Datapoints'));

    $icon_new = id(new PHUIIconView())
      ->setIcon('fa-plus red')
      ->setTooltip(pht('Not Stored'));

    $icon_surplus = id(new PHUIIconView())
      ->setIcon('fa-minus red')
      ->setTooltip(pht('Not Generated'));

    foreach ($engines as $key => $engine) {
      $rows = array();
      foreach ($generated_datapoints[$key] as $datapoint) {
        $dimension_phid = $datapoint->getDimensionPHID();
        if ($dimension_phid !== null) {
          $dimension = $handles[$datapoint->getDimensionPHID()]->renderLink();
        } else {
          $dimension = null;
        }

        $fact_key = $datapoint->getKey();

        $fact = idx($facts[$key], $fact_key, null);
        if ($fact) {
          $fact_label = $fact->getName();
        } else {
          $fact_label = $fact_key;
        }

        $vector_key = $datapoint->newDatapointVector();
        if (isset($stored_map[$key][$vector_key])) {
          unset($stored_map[$key][$vector_key]);
          $icon = $icon_fact;
        } else {
          $icon = $icon_new;
        }

        $rows[] = array(
          $icon,
          $fact_label,
          $dimension,
          $datapoint->getValue(),
          phabricator_datetime($datapoint->getEpoch(), $viewer),
        );
      }

      foreach ($stored_map[$key] as $vector_key => $datapoints) {
        foreach ($datapoints as $datapoint) {
          $dimension_phid = $datapoint['dimensionPHID'];
          if ($dimension_phid !== null) {
            $dimension = $handles[$dimension_phid]->renderLink();
          } else {
            $dimension = null;
          }

          $fact_key = $datapoint['key'];
          $fact = idx($facts[$key], $fact_key, null);
          if ($fact) {
            $fact_label = $fact->getName();
          } else {
            $fact_label = $fact_key;
          }

          $rows[] = array(
            $icon_surplus,
            $fact_label,
            $dimension,
            $datapoint['value'],
            phabricator_datetime($datapoint['epoch'], $viewer),
          );
        }
      }

      foreach ($facts[$key] as $fact) {
        $has_any = id(new PhabricatorFactDatapointQuery())
          ->withFacts(array($fact))
          ->setLimit(1)
          ->execute();
        if ($has_any) {
          continue;
        }

        if (!$has_any) {
          $rows[] = array(
            $icon_nodata,
            $fact->getName(),
            null,
            null,
            null,
          );
        }
      }

      $table = id(new AphrontTableView($rows))
        ->setHeaders(
          array(
            null,
            pht('Fact'),
            pht('Dimension'),
            pht('Value'),
            pht('Date'),
          ))
        ->setColumnClasses(
          array(
            '',
            '',
            '',
            'n wide right',
            'right',
          ));

      $extraction_cost = $timings[$key];
      $extraction_cost = pht(
        '%sms',
        new PhutilNumber((int)(1000 * $extraction_cost)));

      $header = pht(
        '%s (%s)',
        get_class($engine),
        $extraction_cost);

      $box = id(new PHUIObjectBoxView())
        ->setHeaderText($header)
        ->setTable($table);

      $content[] = $box;

      if ($engine instanceof PhabricatorTransactionFactEngine) {
        $groups = $engine->newTransactionGroupsForObject($object);
        $groups = array_values($groups);

        $xaction_phids = array();
        foreach ($groups as $group_key => $xactions) {
          foreach ($xactions as $xaction) {
            $xaction_phids[] = $xaction->getAuthorPHID();
          }
        }
        $xaction_handles = $viewer->loadHandles($xaction_phids);

        $rows = array();
        foreach ($groups as $group_key => $xactions) {
          foreach ($xactions as $xaction) {
            $rows[] = array(
              $group_key,
              $xaction->getTransactionType(),
              $xaction_handles[$xaction->getAuthorPHID()]->renderLink(),
              phabricator_datetime($xaction->getDateCreated(), $viewer),
            );
          }
        }

        $table = id(new AphrontTableView($rows))
          ->setHeaders(
            array(
              pht('Group'),
              pht('Type'),
              pht('Author'),
              pht('Date'),
            ))
          ->setColumnClasses(
            array(
              null,
              'pri',
              'wide',
              'right',
            ));

        $header = pht(
          '%s (Transactions)',
          get_class($engine));

        $xaction_box = id(new PHUIObjectBoxView())
          ->setHeaderText($header)
          ->setTable($table);

        $content[] = $xaction_box;
      }

    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Chart'));

    $title = pht('Chart');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);

  }

}
