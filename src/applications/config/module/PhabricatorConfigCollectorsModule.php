<?php

final class PhabricatorConfigCollectorsModule extends PhabricatorConfigModule {

  public function getModuleKey() {
    return 'collectors';
  }

  public function getModuleName() {
    return pht('Garbage Collectors');
  }

  public function renderModuleStatus(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $collectors = PhabricatorGarbageCollector::getAllCollectors();
    $collectors = msort($collectors, 'getCollectorConstant');

    $rows = array();
    foreach ($collectors as $key => $collector) {
      if ($collector->hasAutomaticPolicy()) {
        $policy_view = phutil_tag('em', array(), pht('Automatic'));
      } else {
        $policy = $collector->getDefaultRetentionPolicy();
        if ($policy === null) {
          $policy_view = pht('Indefinite');
        } else {
          $days = ceil($policy / phutil_units('1 day in seconds'));
          $policy_view = pht(
            '%s Day(s)',
            new PhutilNumber($days));
        }
      }

      $rows[] = array(
        $collector->getCollectorConstant(),
        $collector->getCollectorName(),
        $policy_view,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Constant'),
          pht('Name'),
          pht('Retention Policy'),
        ))
      ->setColumnClasses(
        array(
          null,
          'pri wide',
          null,
        ));

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Garbage Collectors'))
      ->setTable($table);
  }

}
