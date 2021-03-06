<?php

namespace Drupal\webform;

use Drupal\Core\Serialization\Yaml;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;

/**
 * Webform submission generator.
 *
 * @see \Drupal\webform\WebformSubmissionGenerateInterface
 * @see \Drupal\webform\Plugin\DevelGenerate\WebformSubmissionDevelGenerate
 */
class WebformSubmissionGenerate implements WebformSubmissionGenerateInterface {

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The webform element manager.
   *
   * @var \Drupal\webform\WebformElementManagerInterface
   */
  protected $elementManager;

  /**
   * An associative array containing test values for elements by type.
   *
   * @var array
   */
  protected $types;

  /**
   * An associative array containing test values for elements by name.
   *
   * @var array
   */
  protected $names;

  /**
   * Constructs a WebformEmailBuilder object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\webform\WebformElementManagerInterface $element_manager
   *   The webform element manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, Token $token, WebformElementManagerInterface $element_manager) {
    $this->configFactory = $config_factory;
    $this->token = $token;
    $this->elementManager = $element_manager;

    $this->types = Yaml::decode($this->configFactory->get('webform.settings')->get('test.types') ?: '');
    $this->names = Yaml::decode($this->configFactory->get('webform.settings')->get('test.names') ?: '');
  }

  /**
   * {@inheritdoc}
   */
  public function getData(WebformInterface $webform) {
    $elements = $webform->getElementsInitializedAndFlattened();

    $data = [];
    foreach ($elements as $key => $element) {
      $value = $this->getTestValue($webform, $key, $element);
      if ($value !== NULL) {
        $data[$key] = $value;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestValue(WebformInterface $webform, $name, array $element) {
    /** @var \Drupal\webform\WebformElementInterface $element_handler */
    $plugin_id = $this->elementManager->getElementPluginId($element);
    $element_handler = $this->elementManager->createInstance($plugin_id);

    // Exit if element does not have a value.
    if (!$element_handler->isInput($element)) {
      return NULL;
    }

    // Exit if test values are null.
    $values = $this->getTestValues($webform, $name, $element);
    if ($values === NULL) {
      return NULL;
    }

    // Get random test value.
    $value = (is_array($values)) ? $values[array_rand($values)] : $values;

    // Replace tokens.
    $token_data = ['webform' => $webform];
    $token_options = ['clear' => TRUE];
    if (is_string($value)) {
      $value = $this->token->replace($value, $token_data, $token_options);
    }
    elseif (is_array($value)) {
      foreach (array_keys($value) as $value_key) {
        if (is_string($value[$value_key])) {
          $value[$value_key] = $this->token->replace($value[$value_key], $token_data, $token_options);
        }
      }
    }

    // Elements that use multiple values require an array as the
    // default value.
    if ($element_handler->hasMultipleValues($element) && !is_array($value)) {
      return [$value];
    }
    else {
      return $value;
    }
  }

  /**
   * Get test values from a webform element.
   *
   * @param \Drupal\webform\WebformInterface $webform
   *   A webform.
   * @param string $name
   *   The name of the element.
   * @param array $element
   *   The FAPI element.
   *
   * @return array|int|null
   *   An array containing multiple test values or a single test value.
   */
  protected function getTestValues(WebformInterface $webform, $name, array $element) {
    // Get test value from the actual element.
    if (isset($element['#test'])) {
      return $element['#test'];
    }

    // Never populate hidden and value elements.
    if (in_array($element['#type'], ['hidden', 'value'])) {
      return NULL;
    }

    // Invoke WebformElement::test and get a test value.
    // If test value is NULL this element should be populated with test data.
    // @see \Drupal\webform\Plugin\WebformElement\ContainerBase::getTestValue().
    $test_value = $this->elementManager->invokeMethod('getTestValue', $element, $webform);
    if ($test_value) {
      return $test_value;
    }
    elseif ($test_value === NULL) {
      return NULL;
    }

    // Get test values from options.
    if (isset($element['#options'])) {
      return array_keys($element['#options']);
    }

    // Get test values using #type.
    if (isset($this->types[$element['#type']])) {
      return $this->types[$element['#type']];
    }

    // Get test values using on exact name matches.
    if (isset($this->types[$name])) {
      return $this->types[$name];
    }

    // Get test values using partial name matches.
    foreach ($this->names as $key => $values) {
      if (preg_match('/(^|_)' . $key . '(_|$)/i', $name)) {
        return $values;
      }
    }

    // Get test value using #type.
    switch ($element['#type']) {
      case 'range';
      case 'number';
        $element += ['#min' => 1, '#max' => 10];
        return rand($element['#min'], $element['#max']);
    }

    // Get test #unique value.
    if (!empty($element['#unique'])) {
      return uniqid();
    }

    // Return default values.
    return (isset($this->names['default'])) ? $this->names['default'] : NULL;
  }

}
