<?php

namespace Drupal\vbo_export\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * Generates csv.
 *
 * @Action(
 *   id = "vbo_export_generate_csv_action",
 *   label = @Translation("Generate csv from selected view results"),
 *   type = ""
 * )
 */
class VboExportCsv extends VboExportBase {

  const EXTENSION = 'csv';

  /**
   * {@inheritdoc}
   *
   * Add csv separator setting to preliminary config.
   */
  public function buildPreConfigurationForm(array $form, array $values, FormStateInterface $form_state): array {
    $form = parent::buildPreConfigurationForm($form, $values, $form_state);
    $form['separator'] = [
      '#title' => $this->t('CSV separator'),
      '#type' => 'radios',
      '#options' => [
        ';' => $this->t('semicolon ";"'),
        ',' => $this->t('comma ","'),
        '|' => $this->t('pipe "|"'),
      ],
      '#default_value' => $values['separator'] ?? ';',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function generateOutput() {
    $config = $this->configuration;
    $headers = $this->context['sandbox']['header'];
    $rows = $this->getCurrentRows();

    foreach ($rows as &$row) {
      $row = array_map('trim', $row);

      foreach ($row as $key => $value) {
        $new_key = (!array_key_exists($headers[$key], $row))
          ? $headers[$key] : $headers[$key] . ' / ' . $key;

        $row[$new_key] = \html_entity_decode($value);
        unset($row[$key]);
      }
    }

    $serializer = new CsvEncoder([
      CsvEncoder::DELIMITER_KEY => $config['separator'],
      CsvEncoder::OUTPUT_UTF8_BOM_KEY => TRUE,
      CsvEncoder::HEADERS_KEY => $headers,
    ]);

    return $serializer->encode($rows, 'csv');
  }

}
