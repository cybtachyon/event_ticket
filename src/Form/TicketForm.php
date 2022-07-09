<?php

namespace Drupal\event_ticket\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for Ticket edit forms.
 *
 * @ingroup event_ticket
 */
class TicketForm extends ContentEntityForm {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Instantiates this form class.
    $instance = parent::create($container);
    $instance->account = $container->get('current_user');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    if ($route_match->getRawParameter('event_ticket') !== NULL) {
      $entity = $route_match->getParameter('event_ticket');
    }
    else {
      /** @var \Drupal\event\Entity\EventInterface $event */
      $event = $route_match->getParameter('event');
      /** @var \Drupal\event_ticket\Entity\TicketTypeInterface $event_ticket_type */
      $event_ticket_type = $route_match->getParameter('event_ticket_type');
      $values = [
        'type' => $event_ticket_type->id(),
        'event' => $event->id(),
      ];
      $entity = $this->entityTypeManager->getStorage('event_ticket')->create($values);
    }

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\event_ticket\Entity\Ticket $entity */
    $form = parent::buildForm($form, $form_state);

    if (!$this->entity->isNew()) {
      $form['new_revision'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Create new revision'),
        '#default_value' => FALSE,
        '#weight' => 10,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Save as a new revision if requested to do so.
    if (!$form_state->isValueEmpty('new_revision') && $form_state->getValue('new_revision') != FALSE) {
      $entity->setNewRevision();

      // If a new revision is created, save the current user as revision author.
      $entity->setRevisionCreationTime($this->time->getRequestTime());
      $entity->setRevisionUserId($this->account->id());
    }
    else {
      $entity->setNewRevision(FALSE);
    }

    $status = parent::save($form, $form_state);

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addMessage($this->t('Created the %label Ticket.', [
          '%label' => $entity->label(),
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved the %label Ticket.', [
          '%label' => $entity->label(),
        ]));
    }
    $event = $entity->getEvent();
    var_export($event);
    $form_state->setRedirect('entity.event_ticket.canonical', [
      'event' => $event ? $event->id() : NULL,
      'event_ticket' => $entity->id(),
    ]);
  }

}
