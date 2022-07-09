<?php

namespace Drupal\event_ticket\Plugin\Commerce\InlineForm;

use Drupal\commerce\CurrentCountryInterface;
use Drupal\commerce\EntityHelper;
use Drupal\commerce\Plugin\Commerce\InlineForm\ContentEntity;
use Drupal\commerce_order\AddressBookInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\event_registration\Entity\RegistrationInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an inline form for managing an event registration.
 *
 * @CommerceInlineForm(
 *   id = "event_registration",
 *   label = @Translation("Event Registration"),
 * )
 */
class EventRegistration extends ContentEntity {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The customer profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $entity;

  /**
   * Constructs a new CustomerProfile object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_order\AddressBookInterface $address_book
   *   The address book.
   * @param \Drupal\commerce\CurrentCountryInterface $current_country
   *   The current country.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AddressBookInterface $address_book, CurrentCountryInterface $current_country, AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->addressBook = $address_book;
    $this->currentCountry = $current_country;
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('commerce_order.address_book'),
      $container->get('commerce.current_country'),
      $container->get('current_user'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'register',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function requiredConfiguration() {
    return ['form_mode'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(array $inline_form, FormStateInterface $form_state) {
    $inline_form = parent::buildInlineForm($inline_form, $form_state);

    assert($this->entity instanceof RegistrationInterface);

    if (FALSE && $this->shouldRender($inline_form, $form_state)) {
      $view_builder = $this->entityTypeManager->getViewBuilder('event_registration');
      $inline_form['rendered'] = $view_builder->view($this->entity);
      $inline_form['edit_button'] = [
        '#type' => 'button',
        '#name' => $inline_form['#view_mode'] . '_edit',
        '#value' => $this->t('Edit'),
        '#ajax' => [
          'callback' => [get_called_class(), 'ajaxRefresh'],
          'wrapper' => $inline_form['#id'],
        ],
        '#limit_validation_errors' => [$inline_form['#parents']],
        '#attributes' => [
          'class' => ['event-registration-edit-button'],
        ],
      ];
    }
    else {
      $form_display = $this->loadFormDisplay();
      $form_display->buildForm($this->entity, $inline_form, $form_state);
    }

    return $inline_form;
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $inline_form = NestedArray::getValue($form, array_slice($triggering_element['#array_parents'], 0, -1));
    return $inline_form;
  }

  /**
   * Clears form input when select_address is used.
   */
  public static function clearValues(array $element, FormStateInterface $form_state) {
    $user_input = &$form_state->getUserInput();
    $inline_form_input = NestedArray::getValue($user_input, $element['#parents']);
    $inline_form_input = [];
    NestedArray::setValue($user_input, $element['#parents'], $inline_form_input);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function validateInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::validateInlineForm($inline_form, $form_state);

    if (!isset($inline_form['rendered'])) {
      $form_display = $this->loadFormDisplay();
      $form_display->extractFormValues($this->entity, $inline_form, $form_state);
      $form_display->validateFormValues($this->entity, $inline_form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitInlineForm(array &$inline_form, FormStateInterface $form_state) {
    parent::submitInlineForm($inline_form, $form_state);

    if (!isset($inline_form['rendered'])) {
      $form_display = $this->loadFormDisplay();
      $form_display->extractFormValues($this->entity, $inline_form, $form_state);
      $values = $form_state->getValue($inline_form['#parents']);
    }
    $this->entity->save();
  }

  /**
   * Loads a user entity for the given user ID.
   *
   * Falls back to the anonymous user if the user ID is empty or unknown.
   *
   * @param string $uid
   *   The user ID.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity.
   */
  protected function loadUser($uid) {
    $customer = User::getAnonymousUser();
    if (!empty($uid)) {
      $user_storage = $this->entityTypeManager->getStorage('user');
      /** @var \Drupal\user\UserInterface $user */
      $user = $user_storage->load($uid);
      if ($user) {
        $customer = $user;
      }
    }

    return $customer;
  }

  /**
   * Loads the form display used to build the profile form.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface
   *   The form display.
   */
  protected function loadFormDisplay() {
    $form_mode = $this->configuration['form_mode'];
    return EntityFormDisplay::collectRenderDisplay($this->entity, $form_mode);
  }

  /**
   * Determines whether the current profile should be shown rendered.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   *
   * @return bool
   *   TRUE if the profile should be shown rendered, FALSE otherwise.
   */
  protected function shouldRender(array $inline_form, FormStateInterface $form_state) {
    $render_parents = array_merge($inline_form['#parents'], ['render']);
    $triggering_element_name = static::getTriggeringElementName($inline_form, $form_state);
    if ($triggering_element_name == 'edit_button') {
      // The edit button was clicked, turn off profile rendering.
      $form_state->set($render_parents, FALSE);
    }
    $render = $form_state->get($render_parents);
    if (!isset($render)) {
      $render = !$this->isIncomplete($this->entity);
      $form_state->set($render_parents, $render);
    }

    return $render;
  }

  /**
   * Builds the list of options for the given address book profiles.
   *
   * Adds the special _original and _new options.
   *
   * @param \Drupal\profile\Entity\ProfileInterface[] $address_book_profiles
   *   The address book profiles.
   *
   * @return array
   *   The profile options.
   */
  protected function buildOptions(array $address_book_profiles) {
    $profile_options = EntityHelper::extractLabels($address_book_profiles);
    // The customer profile is not new, indicating that it is being edited.
    // Add an _original option to allow the customer to revert their changes
    // after selecting a different option.
    if (!$this->entity->isNew()) {
      $profile_options['_original'] = $this->entity->label();
    }

    return $profile_options;
  }

  /**
   * Checks whether the given profile is incomplete.
   *
   * A profile is incomplete if it has an empty required field.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   *
   * @return bool
   *   TRUE if the given profile is incomplete, FALSE otherwise.
   */
  protected function isIncomplete(ProfileInterface $profile) {
    $violations = $profile->validate();

    return count($violations) > 0;
  }

  /**
   * Determines the name of the triggering element.
   *
   * @param array $inline_form
   *   The inline form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the complete form.
   *
   * @return string
   *   The name of the triggering element, if the triggering element is
   *   a part of the inline form.
   */
  protected static function getTriggeringElementName(array $inline_form, FormStateInterface $form_state) {
    $triggering_element_name = '';
    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element) {
      $parents = array_slice($triggering_element['#parents'], 0, count($inline_form['#parents']));
      if ($inline_form['#parents'] === $parents) {
        $triggering_element_name = end($triggering_element['#parents']);
      }
    }

    return $triggering_element_name;
  }

}
