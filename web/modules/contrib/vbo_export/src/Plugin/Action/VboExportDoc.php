<?php

namespace Drupal\vbo_export\Plugin\Action;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * Generates DOC.
 *
 * @Action(
 *   id = "vbo_export_generate_doc_action",
 *   label = @Translation("Generate doc from selected view results"),
 *   type = "",
 * )
 */
class VboExportDoc extends VboExportBase {

  const EXTENSION = 'docx';

  /**
   * {@inheritdoc}
   */
  protected function generateOutput() {
    $header = $this->getHeader();
    $rows = $this->getCurrentRows();

    // Escape HTML Entities.
    Settings::setOutputEscapingEnabled(TRUE);

    try {
      $word = new PhpWord();
      $section = $word->addSection();

      $word->addTitleStyle(1, ['name' => 'Cambria', 'size' => 28]);
      $section->addTitle($this->view->getTitle() . ' - ' . $this->view->getDisplay()->display['display_title'], 1);

      $word->addFontStyle('bold', ['bold' => TRUE]);
      foreach ($rows as $row) {
        foreach ($header as $field_id => $label) {
          $section->addText($label . ':', 'bold');
          // A Word text run is always plain text, so pass a string: PhpWord's
          // Text::isUTF8() is is_string() gated and would otherwise treat a
          // stringable object as Latin-1 and double-encode UTF-8 characters.
          $section->addText($row[$field_id]);
        }

        // Add an empty line between rows.
        $section->addText(' ');
      }

      $writer = IOFactory::createWriter($word, 'Word2007');

      ob_start();
      $writer->save('php://output');
      return ob_get_clean();
    }
    catch (\Exception $e) {
    }

    return '';
  }

}
