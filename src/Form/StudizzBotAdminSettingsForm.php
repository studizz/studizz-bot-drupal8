<?php

namespace Drupal\studizzbot\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;



/**
 * Configure Studizz Bot settings for this site.
 */
class StudizzBotAdminSettingsForm extends ConfigFormBase 
{

  /**
   * {@inheritdoc}
   */
  public function getFormId() 
  {
    return 'studizzbot_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() 
  {
    return ['studizzbot.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) 
  {
    $config = $this->config('studizzbot.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('General settings'),
      '#open' => TRUE,
    ];

    $form['general']['studizzbot_token'] = [
      '#default_value' => $config->get('token'),
      '#description' => $this->t('This is the Token provided by Studizz'),
      '#maxlength' => 24,
      '#placeholder' => 'Your token',
      '#required' => TRUE,
      '#size' => 24,
      '#title' => $this->t('Studizz Token'),
      '#type' => 'textfield',
    ];

    $form['general']['studizzbot_urls'] = [
    '#default_value' => $config->get('urls'),
    '#description' => $this->t('This is the urls'),
    '#required' => FALSE,
    '#title' => $this->t('Url(s) sur lesquelles le bot ne doit pas apparaître :'),
    '#type' => 'textarea',
    ];


    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) 
  {
    parent::validateForm($form, $form_state);

    // Trim some text values.
    $form_state->setValue('studizzbot_token', trim($form_state->getValue('studizzbot_token')));
    $form_state->setValue('studizzbot_urls', trim($form_state->getValue('studizzbot_urls')));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) 
  {
    $config = $this->config('studizzbot.settings');
    $config
      ->set('token', $form_state->getValue('studizzbot_token'))
      ->set('urls', $form_state->getValue('studizzbot_urls'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Validate a form element that should have tokens in it.
   *
   * For example:
   * @code
   * $form['my_node_text_element'] = [
   *   '#type' => 'textfield',
   *   '#title' => $this->t('Some text to token-ize that has a node context.'),
   *   '#default_value' => 'The title of this node is [node:title].',
   *   '#element_validate' => [[get_class($this), 'tokenElementValidate']],
   * ];
   * @endcode
   */
  public static function tokenElementValidate(&$element, FormStateInterface $form_state) 
  {
    $value = isset($element['#value']) ? $element['#value'] : $element['#default_value'];

    if (!Unicode::strlen($value)) 
    {
      // Empty value needs no further validation since the element should depend
      // on using the '#required' FAPI property.
      return $element;
    }

    $tokens = \Drupal::token()->scan($value);
    $invalid_tokens = static::getForbiddenTokens($tokens);
    if ($invalid_tokens) 
    {
      $form_state->setError($element, t('The %element-title is using the following forbidden tokens with personal identifying information: @invalid-tokens.', ['%element-title' => $element['#title'], '@invalid-tokens' => implode(', ', $invalid_tokens)]));
    }

    return $element;
  }

  /**
   * Get an array of all forbidden tokens.
   *
   * @param array $value
   *   An array of token values.
   *
   * @return array
   *   A unique array of invalid tokens.
   */
  protected static function getForbiddenTokens(array $value) {
    $invalid_tokens = [];
    $value_tokens = is_string($value) ? \Drupal::token()->scan($value) : $value;

    foreach ($value_tokens as $tokens) {
      if (array_filter($tokens, 'static::containsForbiddenToken')) {
        $invalid_tokens = array_merge($invalid_tokens, array_values($tokens));
      }
    }

    array_unique($invalid_tokens);
    return $invalid_tokens;
  }

  /**
   * Validate if string contains forbidden tokens not allowed by privacy rules.
   *
   * @param string $token_string
   *   A string with one or more tokens to be validated.
   *
   * @return bool
   *   TRUE if blacklisted token has been found, otherwise FALSE.
   */
  protected static function containsForbiddenToken($token_string) {
    // List of strings in tokens with personal identifying information not
    // allowed for privacy reasons. See section 8.1 of the Google Analytics
    // terms of use for more detailed information.
    //
    // This list can never ever be complete. For this reason it tries to use a
    // regex and may kill a few other valid tokens, but it's the only way to
    // protect users as much as possible from admins with illegal ideas.
    //
    // User tokens are not prefixed with colon to catch 'current-user' and
    // 'user'.
    //
    // TODO: If someone have better ideas, share them, please!
    $token_blacklist = [
      ':account-name]',
      ':author]',
      ':author:edit-url]',
      ':author:url]',
      ':author:path]',
      ':current-user]',
      ':current-user:original]',
      ':display-name]',
      ':fid]',
      ':mail]',
      ':name]',
      ':uid]',
      ':one-time-login-url]',
      ':owner]',
      ':owner:cancel-url]',
      ':owner:edit-url]',
      ':owner:url]',
      ':owner:path]',
      'user:cancel-url]',
      'user:edit-url]',
      'user:url]',
      'user:path]',
      'user:picture]',
      // addressfield_tokens.module
      ':first-name]',
      ':last-name]',
      ':name-line]',
      ':mc-address]',
      ':thoroughfare]',
      ':premise]',
      // realname.module
      ':name-raw]',
      // token.module
      ':ip-address]',
    ];

    return preg_match('/' . implode('|', array_map('preg_quote', $token_blacklist)) . '/i', $token_string);
  }

  /**
   * The #element_validate callback for create only fields.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The $form_state array for the form this element belongs to.
   *
   * @see form_process_pattern()
   */
  public static function validateCreateFieldValues(array $element, FormStateInterface $form_state) {
    $values = static::extractCreateFieldValues($element['#value']);

    if (!is_array($values)) {
      $form_state->setError($element, t('The %element-title field contains invalid input.', ['%element-title' => $element['#title']]));
    }
    else {
      // Check that name and value are valid for the field type.
      foreach ($values as $name => $value) {
        if ($error = static::validateCreateFieldName($name)) {
          $form_state->setError($element, $error);
          break;
        }
        if ($error = static::validateCreateFieldValue($value)) {
          $form_state->setError($element, $error);
          break;
        }
      }

      $form_state->setValueForElement($element, $values);
    }
  }

  /**
   * Extracts the values array from the element.
   *
   * @param string $string
   *   The raw string to extract values from.
   *
   * @return array|null
   *   The array of extracted key/value pairs, or NULL if the string is invalid.
   *
   * @see \Drupal\options\Plugin\Field\FieldType\ListTextItem::allowedValuesString()
   */
  protected static function extractCreateFieldValues($string) {
    $values = [];

    $list = explode("\n", $string);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $text) {
      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $name = trim($matches[1]);
        $value = trim($matches[2]);
      }
      else {
        return NULL;
      }

      $values[$name] = $value;
    }

    return static::convertFormValueDataTypes($values);
  }

  /**
   * Checks whether a field name is valid.
   *
   * @param string $name
   *   The option value entered by the user.
   *
   * @return string|null
   *   The error message if the specified value is invalid, NULL otherwise.
   */
  protected static function validateCreateFieldName($name) {
    // List of supported field names:
    // https://developers.google.com/analytics/devguides/collection/analyticsjs/field-reference#create
    $create_only_fields = [
      'allowAnchor',
      'alwaysSendReferrer',
      'clientId',
      'cookieName',
      'cookieDomain',
      'cookieExpires',
      'legacyCookieDomain',
      'legacyHistoryImport',
      'sampleRate',
      'siteSpeedSampleRate',
      'storage',
      'useAmpClientId',
      'userId',
    ];

    if ($name == 'name') {
      return t('Create only field name %name is a disallowed field name. Changing the <em>Tracker Name</em> is currently not supported.', ['%name' => $name]);
    }
    if ($name == 'allowLinker') {
      return t('Create only field name %name is a disallowed field name. Please select <em>Multiple top-level domains</em> under <em>What are you tracking</em> to enable cross domain tracking.', ['%name' => $name]);
    }
    if (!in_array($name, $create_only_fields)) {
      return t('Create only field name %name is an unknown field name. Field names are case sensitive. Please see <a href=":url">create only fields</a> documentation for supported field names.');
    }
  }

  /**
   * Checks whether a candidate value is valid.
   *
   * @param string $value
   *   The option value entered by the user.
   *
   * @return string|null
   *   The error message if the specified value is invalid, NULL otherwise.
   */
  protected static function validateCreateFieldValue($value) {
    if (!is_bool($value) && !Unicode::strlen($value)) {
      return t('A create only field requires a value.');
    }
    if (Unicode::strlen($value) > 255) {
      return t('The value of a create only field must be a string at most 255 characters long.');
    }
  }

  /**
   * Generates a string representation of an array.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param array $values
   *   An array of values, where array keys are values and array values are
   *   labels.
   *
   * @return string
   *   The string representation of the $values array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "name|value" or "value".
   */
  protected function getNameValueString(array $values) {
    $lines = [];
    foreach ($values as $name => $value) {
      // Convert data types.
      if (is_bool($value)) {
        $value = ($value) ? 'true' : 'false';
      }

      $lines[] = "$name|$value";
    }
    return implode("\n", $lines);
  }

  /**
   * Prepare form data types for Json conversion.
   *
   * @param array $values
   *   Array of values.
   *
   * @return string
   *   Value with casted data type.
   */
  protected static function convertFormValueDataTypes(array $values) {

    foreach ($values as $name => $value) {
      // Convert data types.
      $match = Unicode::strtolower($value);
      if ($match == 'true') {
        $value = TRUE;
      }
      elseif ($match == 'false') {
        $value = FALSE;
      }

      // Convert other known fields.
      switch ($name) {
        case 'sampleRate':
          // Float types.
          settype($value, 'float');
          break;

        case 'siteSpeedSampleRate':
        case 'cookieExpires':
          // Integer types.
          settype($value, 'integer');
          break;
      }

      $values[$name] = $value;
    }

    return $values;
  }

}
