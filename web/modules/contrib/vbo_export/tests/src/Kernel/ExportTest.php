<?php

declare(strict_types=1);

namespace Drupal\Tests\vbo_export\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Site\Settings;
use Drupal\Tests\views_bulk_operations\Kernel\ViewsBulkOperationsKernelTestBase;
use Drupal\vbo_export\Plugin\Action\VboExportBase;
use Drupal\vbo_export\Plugin\Action\VboExportCsv;
use Drupal\vbo_export\Plugin\Action\VboExportDoc;
use Drupal\vbo_export\Plugin\Action\VboExportPdf;
use Drupal\vbo_export\Plugin\Action\VboExportXlsx;
use Drupal\views\Views;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Serializer\Encoder\CsvEncoder;

/**
 * Tests the export actions and the content of the files they generate.
 */
#[Group('vbo_export')]
#[CoversClass(VboExportBase::class)]
#[CoversClass(VboExportCsv::class)]
#[CoversClass(VboExportXlsx::class)]
#[CoversClass(VboExportDoc::class)]
#[CoversClass(VboExportPdf::class)]
class ExportTest extends ViewsBulkOperationsKernelTestBase {

  private const SEPARATOR = ',';

  /**
   * Result rows deltas selected for export.
   */
  private const SELECTION = [0, 1, 2, 3];

  /**
   * A field label containing UTF-8 (Polish) characters.
   */
  private const POLISH_LABEL = 'Termin płatności';

  /**
   * A field value containing UTF-8 (Polish) characters.
   */
  private const POLISH_VALUE = 'Zażółć gęślą jaźń';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'vbo_export',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');

    $this->createTestNodes([
      'page' => [
        'count' => 5,
      ],
    ]);
  }

  /**
   * Tests the csv export.
   */
  public function testCsvExport(): void {
    $contents = $this->generateExport('vbo_export_generate_csv_action', [
      'separator' => self::SEPARATOR,
    ], 'csv');

    $serializer = new CsvEncoder([
      CsvEncoder::DELIMITER_KEY => self::SEPARATOR,
      CsvEncoder::OUTPUT_UTF8_BOM_KEY => TRUE,
    ]);
    $rows = $serializer->decode($contents, 'csv');

    foreach ($this->testNodesData as $nid => $item) {
      $i = $nid - 1;

      if (\in_array($i, self::SELECTION, TRUE)) {
        self::assertEquals($item['en'], $rows[$i]['Title'], "Exported node title doesn't match");
      }
      else {
        self::assertArrayNotHasKey($i, $rows);
      }
    }
  }

  /**
   * Tests the xlsx export.
   *
   * A xlsx file is a zipped set of XML parts; here we round-trip it through
   * the PhpSpreadsheet reader and assert the header and cell values.
   */
  public function testXlsxExport(): void {
    $contents = $this->generateExport('vbo_export_generate_xlsx_action', [], 'xlsx');

    $file = $this->saveToTempFile($contents, 'xlsx');
    $worksheet = IOFactory::load($file)->getActiveSheet();

    // The single visible view field, in the first (header) row.
    self::assertSame('Title', $worksheet->getCell('A1')->getValue());

    // Selected node titles, one per row below the header.
    foreach (self::SELECTION as $row_index => $delta) {
      self::assertSame(
        'Title ' . $delta,
        $worksheet->getCell('A' . ($row_index + 2))->getValue(),
        "Exported node title doesn't match",
      );
    }

    // No extra rows beyond the header and the selection were written.
    self::assertSame(\count(self::SELECTION) + 1, $worksheet->getHighestRow());
  }

  /**
   * Tests the docx export.
   *
   * A docx file is a zip archive; the text lives in word/document.xml, so we
   * read that part directly and assert the labels and values are present.
   */
  public function testDocxExport(): void {
    $contents = $this->generateExport('vbo_export_generate_doc_action', [], 'docx');

    $document = $this->readZipEntry($contents, 'docx', 'word/document.xml');

    // The field label is repeated once per exported row.
    self::assertSame(\count(self::SELECTION), \substr_count($document, 'Title:'));

    foreach (self::SELECTION as $delta) {
      self::assertStringContainsString('Title ' . $delta, $document, "Exported node title doesn't match");
    }
  }

  /**
   * Tests the pdf export.
   *
   * A pdf is not an XML container, so we only assert a well-formed document
   * was produced, matching the level of verification core dompdf tests use.
   */
  public function testPdfExport(): void {
    $contents = $this->generateExport('vbo_export_generate_pdf_action', [
      'paper_size' => 'letter',
      'orientation' => 'portrait',
    ], 'pdf');

    self::assertStringStartsWith('%PDF-', $contents);
    self::assertStringEndsWith("%%EOF\n", $contents);
  }

  /**
   * The docx export preserves UTF-8 characters in labels and values.
   *
   * Exercises the strip_tags = FALSE path, which used to hand PhpWord a
   * stringable object that its is_string()-gated UTF-8 detection mistook for
   * Latin-1 and double-encoded.
   */
  public function testDocxExportUtf8(): void {
    $document = $this->readZipEntry(
      $this->generatePolishExport('vbo_export_generate_doc_action', 'docx'),
      'docx',
      'word/document.xml',
    );

    self::assertStringContainsString(self::POLISH_LABEL, $document, 'Polish label survived');
    self::assertStringContainsString(self::POLISH_VALUE, $document, 'Polish value survived');
    // "Å¼" is the tell-tale of the ISO-8859-1 double-encoding regression.
    self::assertStringNotContainsString('Å¼', $document, 'No double-encoding occurred');
  }

  /**
   * The pdf export embeds a Unicode font so non-Latin1 glyphs can render.
   *
   * The generated text stream is compressed and CID-encoded, so it cannot be
   * asserted directly here; instead we assert a well-formed PDF that embeds a
   * font with the required glyph coverage (the core fonts drop them as "?").
   */
  public function testPdfExportUtf8(): void {
    $contents = $this->generatePolishExport('vbo_export_generate_pdf_action', 'pdf');

    self::assertStringStartsWith('%PDF-', $contents);
    self::assertStringContainsString('DejaVuSans', $contents, 'Unicode font embedded');
  }

  /**
   * Runs an export whose header label and one value contain UTF-8 characters.
   *
   * @param string $action_id
   *   The export action plugin ID.
   * @param string $extension
   *   The expected generated file extension.
   *
   * @return string
   *   The raw contents of the generated file.
   */
  private function generatePolishExport(string $action_id, string $extension): string {
    $this->drupalCreateNode(['type' => 'page', 'title' => self::POLISH_VALUE]);

    return $this->generateExport($action_id, [
      'strip_tags' => FALSE,
      'field_override' => TRUE,
      'field_config' => [
        'title' => ['active' => TRUE, 'label' => self::POLISH_LABEL],
      ],
      'paper_size' => 'letter',
      'orientation' => 'portrait',
    ], $extension, [0, 1, 2, 3, 4, 5]);
  }

  /**
   * Tests the file scheme options of the pre-configuration form.
   *
   * Only writable, visible schemes may be picked as an export destination.
   * The read-only extension wrappers (module://, theme://, …) that core
   * registers must be excluded, otherwise building the form throws.
   */
  public function testPreConfigurationFormFileScheme(): void {
    $view = Views::getView('views_bulk_operations_test');
    $view->setDisplay('default');
    $view->initHandlers();

    /** @var \Drupal\vbo_export\Plugin\Action\VboExportBase $action */
    $action = $this->container
      ->get('plugin.manager.views_bulk_operations_action')
      ->createInstance('vbo_export_generate_csv_action');
    $action->setView($view);

    $form = $action->buildPreConfigurationForm([], [], new FormState());

    self::assertArrayHasKey('file_scheme', $form);
    $options = $form['file_scheme']['#options'];
    self::assertArrayHasKey('public', $options);
    self::assertArrayNotHasKey('module', $options);
    self::assertArrayNotHasKey('theme', $options);
  }

  /**
   * Runs an export action and returns the generated file contents.
   *
   * @param string $action_id
   *   The export action plugin ID.
   * @param array $configuration
   *   Action configuration, merged over the defaults shared by all exports.
   * @param string $extension
   *   The expected generated file extension.
   * @param array $selection
   *   Result rows deltas to export.
   *
   * @return string
   *   The raw contents of the generated file.
   */
  protected function generateExport(string $action_id, array $configuration, string $extension, array $selection = self::SELECTION): string {
    $vbo_data = [
      'view_id' => 'views_bulk_operations_test',
      'action_id' => $action_id,
      'configuration' => $configuration + [
        'field_override' => FALSE,
        'strip_tags' => TRUE,
      ],
    ];
    $vbo_data['list'] = $this->getResultsList($vbo_data, $selection);

    $this->executeAction($vbo_data);

    $messenger = $this->container->get('messenger');
    $messages = $messenger->messagesByType($messenger::TYPE_STATUS);
    self::assertNotEmpty($messages, 'The export produced a status message.');

    \preg_match('#views_bulk_operations_test_.*\.' . $extension . '#', \html_entity_decode((string) $messages[0]), $matches);
    self::assertNotEmpty($matches, 'The status message links to the generated file.');

    $file_path = Settings::get('file_public_path') . '/' . $matches[0];
    self::assertFileExists($file_path);

    return \file_get_contents($file_path);
  }

  /**
   * Reads a single entry out of a zip-based document held in memory.
   *
   * @param string $contents
   *   The raw document (docx/xlsx) contents.
   * @param string $extension
   *   The document extension, used for the temporary file name.
   * @param string $entry
   *   The archive entry to read, e.g. "word/document.xml".
   *
   * @return string
   *   The entry contents.
   */
  protected function readZipEntry(string $contents, string $extension, string $entry): string {
    $file = $this->saveToTempFile($contents, $extension);

    $zip = new \ZipArchive();
    self::assertTrue($zip->open($file) === TRUE, 'The generated document is a valid zip archive.');
    $data = $zip->getFromName($entry);
    $zip->close();

    self::assertNotFalse($data, \sprintf('The archive contains the "%s" entry.', $entry));

    return $data;
  }

  /**
   * Writes contents to a temp file, for readers that need a path to open.
   *
   * @param string $contents
   *   The raw file contents.
   * @param string $extension
   *   The file extension to use.
   *
   * @return string
   *   The temporary file path.
   */
  protected function saveToTempFile(string $contents, string $extension): string {
    $file = \tempnam(\sys_get_temp_dir(), 'vbo_export_') . '.' . $extension;
    \file_put_contents($file, $contents);

    return $file;
  }

}
