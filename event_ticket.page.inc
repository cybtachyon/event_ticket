<?php

/**
 * @file
 * Contains event_ticket.page.inc.
 *
 * Page callback for Ticket entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for Ticket templates.
 *
 * Default template: event_ticket.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_event_ticket(array &$variables) {
  // Fetch Ticket Entity Object.
  $event_ticket = $variables['elements']['#event_ticket'];

  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
