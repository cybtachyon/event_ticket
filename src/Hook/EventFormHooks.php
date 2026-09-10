<?php

namespace Drupal\event_ticket\Hook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;


/**
 * Alters the user forms to support Organization Groups.
 */
class EventFormHooks {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    #[Autowire(service: 'entity_field.manager')] private readonly EntityFieldManagerInterface $entity_field_manager,
    #[Autowire(service: 'entity_type.manager')] private readonly EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for event_type_form.
   */
  #[Hook('form_event_type_form_alter')]
  public function formEventTypeFormAlter(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\event\Entity\EventTypeInterface $event_type */
    $event_type = $form_state->getFormObject()->getEntity();

    // @todo Loop through ticket types for the checkbox here.
    /** @var \Drupal\event_ticket\Entity\TicketTypeInterface[] $ticket_type_entities */
    $ticket_type_entities = $this->entityTypeManager->getStorage('event_ticket_type')->loadMultiple();
    $type_checkbox_options = [];
    foreach ($ticket_type_entities as $ticket_type_entity) {
      $type_checkbox_options[$ticket_type_entity->id()] = $ticket_type_entity->label();
    }

    $form['event_group']['event_ticket_type'] = array(
      '#default_value' => $event_type->getThirdPartySetting('event_ticket', 'event_ticket_type', []),
      '#description' => $this->t('Select <a href="@link">ticket types</a> this event supports.', ['@link' => Url::fromRoute('entity.event_ticket_type.collection')->toString()]),
      '#options' => $type_checkbox_options,
      '#type' => 'checkboxes',
      '#title' => $this->t('Enable Event Ticket Type'),
    );

    $form['#entity_builders'][] = 'Drupal\event_ticket\Hook\EventFormHooks::eventTypeFormBuilder';
  }

  /**
   * Adds event_ticket_type to third party settings.
   */
  public function eventTypeFormBuilder($entity_type, EntityInterface $type, &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\event\Entity\EventTypeInterface $type */
    $event_ticket_types = array_filter($form_state->getValue('event_ticket_type'));
    if (!empty($event_ticket_types)) {
      $type->setThirdPartySetting('event_ticket', 'event_ticket_type', $event_ticket_types);
      // @todo Update the field configuration? Is this dangerous?
      $this->entityFieldManager->clearCachedFieldDefinitions();
      return;
    }

    $type->unsetThirdPartySetting('event_ticket', 'event_ticket_type');
  }

  /**
   * The Drupal Entity Field Manager.
   */
  private readonly EntityFieldManagerInterface $entityFieldManager;

  /**
   * The Drupal Entity Type Manager.
   */
  private readonly EntityTypeManagerInterface $entityTypeManager;

}
