<?php

namespace Drupal\vbo_export\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for vbo_export.
 */
class VboExportHooks {

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help(string $route_name, RouteMatchInterface $route_match): ?string {
    if ($route_name === 'help.page.vbo_export') {
      // This class lives in src/Hook, so the module root is three levels up.
      $filepath = \dirname(__FILE__, 3) . '/README.md';
      if (\file_exists($filepath)) {
        return '<pre>' . \file_get_contents($filepath) . '</pre>';
      }
    }

    return NULL;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'vbo_export_pdf' => [
        'variables' => [
          'title' => NULL,
          'items' => [],
          'empty_text' => NULL,
          // For preprocessing purposes.
          'view_id' => NULL,
          'display_id' => NULL,
        ],
      ],
    ];
  }

}
