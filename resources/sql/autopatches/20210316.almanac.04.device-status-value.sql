UPDATE {$NAMESPACE}_almanac.almanac_device
  SET status = 'active' WHERE status = '';
