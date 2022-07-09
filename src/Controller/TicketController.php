<?php

namespace Drupal\event_ticket\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\event\Entity\EventInterface;
use Drupal\event_ticket\Entity\TicketInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class TicketController.
 *
 *  Returns responses for Ticket routes.
 */
class TicketController extends EntityController implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($entity_type_id, EventInterface $event = null) {
    
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $bundle_key = $entity_type->getKey('bundle');
    $bundle_entity_type_id = $entity_type->getBundleEntityType();
    $build = [
      '#theme' => 'entity_add_list',
      '#bundles' => [],
    ];
    if ($bundle_entity_type_id) {
      $bundle_argument = $bundle_entity_type_id;
      $bundle_entity_type = $this->entityTypeManager->getDefinition($bundle_entity_type_id);
      $bundle_entity_type_label = $bundle_entity_type->getSingularLabel();
      $build['#cache']['tags'] = $bundle_entity_type->getListCacheTags();

      // Build the message shown when there are no bundles.
      $link_text = $this->t('Add a new @entity_type.', ['@entity_type' => $bundle_entity_type_label]);
      $link_route_name = 'entity.' . $bundle_entity_type->id() . '.add_form';
      $build['#add_bundle_message'] = $this->t('There is no @entity_type yet. @add_link', [
        '@entity_type' => $bundle_entity_type_label,
        '@add_link' => Link::createFromRoute($link_text, $link_route_name)->toString(),
      ]);
      // Filter out the bundles the user doesn't have access to.
      $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type_id);
      foreach ($bundles as $bundle_name => $bundle_info) {
        $access = $access_control_handler->createAccess($bundle_name, NULL, [], TRUE);
        if (!$access->isAllowed()) {
          unset($bundles[$bundle_name]);
        }
        $this->renderer->addCacheableDependency($build, $access);
      }
      // Add descriptions from the bundle entities.
      $bundles = $this->loadBundleDescriptions($bundles, $bundle_entity_type);
    }
    else {
      $bundle_argument = $bundle_key;
    }

    $form_route_name = 'entity.' . $entity_type_id . '.add_form';
    // Redirect if there's only one bundle available.
    if (count($bundles) == 1) {
      $bundle_names = array_keys($bundles);
      $bundle_name = reset($bundle_names);
      return $this->redirect($form_route_name, [
        $bundle_argument => $bundle_name,
        'event' => $event->id(),
      ]);
    }

    // Prepare the #bundles array for the template.
    foreach ($bundles as $bundle_name => $bundle_info) {
      $build['#bundles'][$bundle_name] = [
        'label' => $bundle_info['label'],
        'description' => $bundle_info['description'] ?? '',
        'add_link' => Link::createFromRoute($bundle_info['label'], $form_route_name, [
          $bundle_argument => $bundle_name,
          'event' => $event->id(),
        ]),
      ];
    }

    return $build;
  }

  /**
   * Displays a Ticket revision.
   *
   * @param int $event_ticket_revision
   *   The Ticket revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($event_ticket_revision) {
    $event_ticket = $this->entityTypeManager()->getStorage('event_ticket')
      ->loadRevision($event_ticket_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('event_ticket');

    return $view_builder->view($event_ticket);
  }

  /**
   * Page title callback for a Ticket revision.
   *
   * @param int $event_ticket_revision
   *   The Ticket revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($event_ticket_revision) {
    $event_ticket = $this->entityTypeManager()->getStorage('event_ticket')
      ->loadRevision($event_ticket_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $event_ticket->label(),
      '%date' => $this->dateFormatter->format($event_ticket->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a Ticket.
   *
   * @param \Drupal\event_ticket\Entity\TicketInterface $event_ticket
   *   A Ticket object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(TicketInterface $event_ticket) {
    $account = $this->currentUser();
    $event_ticket_storage = $this->entityTypeManager()->getStorage('event_ticket');

    $langcode = $event_ticket->language()->getId();
    $langname = $event_ticket->language()->getName();
    $languages = $event_ticket->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $event_ticket->label()]) : $this->t('Revisions for %title', ['%title' => $event_ticket->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all ticket revisions") || $account->hasPermission('administer ticket entities')));
    $delete_permission = (($account->hasPermission("delete all ticket revisions") || $account->hasPermission('administer ticket entities')));

    $rows = [];

    $vids = $event_ticket_storage->revisionIds($event_ticket);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\event_ticket\TicketInterface $revision */
      $revision = $event_ticket_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $event_ticket->getRevisionId()) {
          $link = $this->l($date, new Url('entity.event_ticket.revision', [
            'event_ticket' => $event_ticket->id(),
            'event_ticket_revision' => $vid,
          ]));
        }
        else {
          $link = $event_ticket->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.event_ticket.translation_revert', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.event_ticket.revision_revert', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.event_ticket.revision_delete', [
                'event_ticket' => $event_ticket->id(),
                'event_ticket_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['event_ticket_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
