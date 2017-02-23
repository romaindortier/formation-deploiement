<?php

namespace Drupal\config_override_test;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * Tests module overrides for configuration.
 */
class ConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = array();
    $account = \Drupal::currentUser();
    
    if($account->isAnonymous()){
      $overrides = array('system.site' => array('name' => 'Drupal 8'));
    }else{
      $overrides = array('system.site' => array('name' => 'Drupal 8 Intranet'));
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'ConfigOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

}
