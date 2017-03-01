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
    $rowc = array();
    foreach ($collectors as $key => $collector) {
      $class = null;
      if ($collector->hasAutomaticPolicy()) {
        $policy_view = phutil_tag('em', array(), pht('Automatic'));
      } else {
        $policy = $collector->getRetentionPolicy();
        if ($policy === null) {
          $policy_view = pht('Indefinite');
        } else {
          $days = ceil($policy / phutil_units('1 day in seconds'));
          $policy_view = pht(
            '%s Day(s)',
            new PhutilNumber($days));
        }

        $default = $collector->getDefaultRetentionPolicy();
        if ($policy !== $default) {
          $class = 'highlighted';
          $policy_view = phutil_tag('strong', array(), $policy_view);
        }
      }

      $rowc[] = $class;
      $rows[] = array(
        $collector->getCollectorConstant(),
        $collector->getCollectorName(),
        $policy_view,
      );
    }

    $info = id(new PHUIInfoView())
      ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
      ->appendChild(pht(
          'Collectors with custom policies are highlighted. Use '.
          '%s to change retention policies.',
          phutil_tag('tt', array(), 'bin/garbage set-policy')));

    $table = id(new AphrontTableView($rows))
      ->setNotice($info)
      ->setRowClasses($rowc)
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

    return $table;
  }

}
