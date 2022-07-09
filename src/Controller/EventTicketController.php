<?php

namespace Drupal\event_ticket\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormState;
use Drupal\event\Entity\EventInterface;
use Drupal\event_ticket\Form\TicketAddToCartForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides controller for an event's registration overview page.
 */
class EventTicketController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->formBuilder = $container->get('form_builder');
    return $instance;
  }

  /**
   * The controller title callback.
   *
   * @param \Drupal\event\Entity\EventInterface $event
   *   The routes event.
   *
   * @return string
   *   The page title.
   */
  public function overviewTitle(EventInterface $event) {
    return $this->t('%event Tickets', [
      '%event' => $event->label(),
    ]);
  }

  /**
   * Displays a list of available registration types for an event.
   *
   * @param \Drupal\event\Entity\EventInterface $event
   *   The routes event.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function overview(EventInterface $event) {
    $form_state = new FormState();
    $form_state->addBuildInfo('event', $event);
    return \Drupal::formBuilder()->buildForm(TicketAddToCartForm::class, $form_state);
  }

  /**
   * The controller title callback.
   *
   * @param \Drupal\event\Entity\EventInterface $event
   *   The routes event.
   *
   * @return string
   *   The page title.
   */
  public function listTitle(EventInterface $event) {
    return $this->t('%event Tickets', [
      '%event' => $event->label(),
    ]);
  }

}
