<?php

namespace Drupal\event_ticket\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormState;
use Drupal\event_ticket\Form\TicketAddToCartForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'event_ticket_add_to_cart' formatter.
 *
 * @FieldFormatter(
 *   id = "event_ticket_add_to_cart",
 *   label = @Translation("Add to cart form"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   dependencies = {
 *     "commerce_cart",
 *   },
 * )
 */
class AddToCartFormatter extends FormatterBase {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'combine' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $form['combine'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Combine order items containing the same product variation.'),
      '#description' => $this->t('The order item type, referenced product variation, and data from fields exposed on the Add to Cart form must all match to combine.'),
      '#default_value' => $this->getSetting('combine'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->getSetting('combine')) {
      $summary[] = $this->t('Combine order items containing the same product variation.');
    }
    else {
      $summary[] = $this->t('Do not combine order items containing the same product variation.');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    $event = $items->getEntity();
    if (!empty($event->in_preview)) {
      $elements[0]['add_to_cart_form'] = [
        '#type' => 'actions',
        ['#type' => 'button', '#value' => $this->t('Add to cart')],
      ];
      return $elements;
    }
    if ($event->isNew()) {
      return [];
    }

    $form_state = new FormState();
    $form_state->addBuildInfo('event', $event);
    return \Drupal::formBuilder()->buildForm(TicketAddToCartForm::class, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $has_cart = \Drupal::moduleHandler()->moduleExists('event_ticket');
    $entity_type = $field_definition->getTargetEntityTypeId();
    $field_name = $field_definition->getName();
    return $has_cart && $entity_type == 'event' && $field_name == 'event_tickets';
  }

}
