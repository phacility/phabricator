<?php

final class ShoppingCarApplication extends PhabricatorApplication {
    
    public function getName() {
        return pht('ShoppingCar');
    }
    
    public function getBaseURI() {
        return '/shoppingcar/';
    }
    
    public function getShortDescription() {
        return pht('Test Shopping Car ');
    }
    
    public function getIcon() {
        return 'fa-dashboard';
    }
    
    public function isPinnedByDefault(PhabricatorUser $viewer) {
        return true;
    }
    
    public function getApplicationOrder() {
        return 0.160;
    }
    
    public function getRoutes() {
        return array(
            '/shoppingcar/(?:(?P<view>\w+)/)?' => 'ShoppingCarController'
        );
    }
    
    public function getRemarkupRules() {
        return array(
            new PhabricatorDashboardRemarkupRule(),
        );
    }
    
}