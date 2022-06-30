<?php

namespace Drupal\event_ticket\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\event_ticket\Entity\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the review pane.
 *
 * @CommerceCheckoutPane(
 *   id = "event_ticket_information",
 *   label = @Translation("Registration Infromation"),
 *   default_step = "event_ticket_information",
 * )
 */
class Registration extends CheckoutPaneBase {

  protected $displayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, CheckoutFlowInterface $checkout_flow = NULL) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition, $checkout_flow);
    $instance->displayRepository = $container->get('entity_display.repository');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'form_mode' => 'register',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationSummary() {
    return $this->t('Form Mode: %form_mode', [
      '%form_mode' => $this->configuration['form_mode'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form_modes = $this->displayRepository->getFormModeOptions('event_registration');

    $form['form_mode'] = [
      '#type' => 'options',
      '#title' => $this->t('Form Mode'),
      '#options' => $form_modes,
      '#description' => $this->t('Registration information form mode.'),
      '#default_value' => $this->configuration['form_mode'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['form_mode'] = !empty($values['form_mode']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isVisible() {
    $visible = FALSE;
    foreach ($this->order->getItems() as $item) {
      if ($item->getPurchasedEntity() instanceof TicketInterface) {
        $visible = TRUE;
        break;
      }
    }
    // Show the pane only when there is a registration that needs info.
    return $visible;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneSummary() {
    $storage = $this->entityTypeManager->getStorage('event_registration');

    $summary = [];
    foreach ($order->getItems() as $item) {
      $registrations_query = $storage->getQuery();
      $registrations_query->condition('order_item', $item->id());
      $registration_ids = $registration_query->execute();
      $registrations = $storage->loadMultiple($registration_ids);
      foreach ($registrations as $registration) {
        $summary[$registration->id()] = [
          '#plain_text' => $registration->getLabel(),
        ];
      }
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {


    $summary = [];
    foreach ($order->getItems() as $item) {
      $registrations = $this->getOrderItemRegistrations($item);
      foreach ($registrations as $registration) {
        $summary[$registration->id()] = [
          '#plain_text' => $registration->getLabel(),
        ];
      }

      $new = $item->getQuantity() - count($registrations);
      if ($new < 0) {
        $form_state->setError($pane_form, $this->t('There are more registrations then tickets being purchased.');
      }
      else if ($new > 0) {
        //@TODO: Add entry form.
      }
    }

    return $pane_form;
  }

  public function getOrderItemRegistrations(OrderItemInterface $order_item) {
    $storage = $this->entityTypeManager->getStorage('event_registration');
    $registrations_query = $storage->getQuery();
    $registrations_query->condition('order_item', $item->id());
    $registration_ids = $registration_query->execute();
    return $storage->loadMultiple($registration_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if ($this->configuration['double_entry'] && $values['email'] != $values['email_confirm']) {
      $form_state->setError($pane_form, $this->t('The specified emails do not match.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setEmail($values['email']);
  }

}
