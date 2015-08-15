<?php

abstract class PhabricatorMailOutboundRoutingHeraldAction
  extends HeraldAction {

  const DO_ROUTE = 'do.route';

  public function supportsObject($object) {
    return ($object instanceof PhabricatorMetaMTAMail);
  }

  public function getActionGroupKey() {
    return HeraldApplicationActionGroup::ACTIONGROUPKEY;
  }

  protected function applyRouting(HeraldRule $rule, $route, $phids) {
    $adapter = $this->getAdapter();
    $mail = $adapter->getObject();
    $mail->addRoutingRule($route, $phids, $rule->getPHID());

    $this->logEffect(
      self::DO_ROUTE,
      array(
        'route' => $route,
        'phids' => $phids,
      ));
  }

  protected function getActionEffectMap() {
    return array(
      self::DO_ROUTE => array(
        'icon' => 'fa-arrow-right',
        'color' => 'green',
        'name' => pht('Routed Message'),
      ),
    );
  }

  protected function renderActionEffectDescription($type, $data) {
    switch ($type) {
      case self::DO_ROUTE:
        return pht('Routed mail.');
    }
  }

}
