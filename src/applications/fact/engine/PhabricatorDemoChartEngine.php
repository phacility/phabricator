<?php

final class PhabricatorDemoChartEngine
  extends PhabricatorChartEngine {

  const CHARTENGINEKEY = 'facts.demo';

  protected function newChart(PhabricatorFactChart $chart, array $map) {
    $viewer = $this->getViewer();

    $functions = array();

    $function = $this->newFunction(
      array('scale', 0.0001),
      array('cos'),
      array('scale', 128),
      array('shift', 256));

    $function->getFunctionLabel()
      ->setKey('cos-x')
      ->setName(pht('cos(x)'))
      ->setColor('rgba(0, 200, 0, 1)')
      ->setFillColor('rgba(0, 200, 0, 0.15)');

    $functions[] = $function;

    $function = $this->newFunction(
      array('constant', 345));

    $function->getFunctionLabel()
      ->setKey('constant-345')
      ->setName(pht('constant(345)'))
      ->setColor('rgba(0, 0, 200, 1)')
      ->setFillColor('rgba(0, 0, 200, 0.15)');

    $functions[] = $function;

    $datasets = array();

    $datasets[] = id(new PhabricatorChartStackedAreaDataset())
      ->setFunctions($functions);

    $chart->attachDatasets($datasets);
  }

}
