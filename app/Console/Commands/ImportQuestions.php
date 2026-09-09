<?php

namespace App\Console\Commands;

use App\Models\Flashcard;
use App\Models\Question;
use App\Models\Section;
use App\Models\Specialty;
use App\Models\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportQuestions extends Command
{
    protected $signature = 'questions:import
                            {file? : Ruta al archivo .xlsx (default: MCQ_CLNICA_1.xlsx)}
                            {--sheet= : Nombre de la hoja (default: Notes o Hoja 1)}
                            {--from-row= : Forzar fila inicial para importación}
                            {--all : Importar todo desde la fila 2 ignorando el estado previo}
                            {--fresh : Borra todas las preguntas y reinicia el estado antes de importar}';

    protected $description = 'Importa preguntas del banco MCQ y Flashcards de forma incremental o completa.';

    private int $imported = 0;
    private int $flashcards = 0;
    private int $skipped = 0;
    private int $lastProcessedRow = 1;
    private int $lastProcessedId = 0;

    private array $skipReasons = [
        'no_code' => 0,
        'no_question' => 0,
        'bad_answer' => 0,
        'no_specialty' => 0,
        'no_topic' => 0,
        'parse_fail' => 0,
        'answer_not_in_options' => 0,
        'corrupt_text' => 0,
    ];

    private array $errors = [];

    public function handle(): int
    {
        $file = $this->argument('file');
        if (! $file) {
            // Buscar en orden: argumento > database/imports/ > raíz (legacy)
            $candidates = [
                base_path('database/imports/MCQ_CLNICA_1.xlsx'),
                base_path('database/imports/BANCO_MCQ_CLINICA_1.xlsx'),
                base_path('MCQ_CLNICA_1.xlsx'),
                base_path('BANCO_MCQ_CLINICA_1.xlsx'),
            ];
            foreach ($candidates as $candidate) {
                if (file_exists($candidate)) {
                    $file = $candidate;
                    break;
                }
            }
        }

        if (! file_exists($file)) {
            $this->error("No encuentro el archivo: {$file}");
            return self::FAILURE;
        }

        $statePath = storage_path('app/import_state.json');
        $state = $this->loadState($statePath);

        if ($this->option('fresh')) {
            if (! $this->confirm('Vas a borrar TODAS las preguntas, flashcards, especialidades, temas y secciones. ¿Seguimos?', false)) {
                $this->warn('Cancelado.');
                return self::FAILURE;
            }
            DB::transaction(function () {
                Question::query()->delete();
                Flashcard::query()->delete();
                Topic::query()->delete();
                Specialty::query()->delete();
                Section::query()->delete();
            });
            $state = [
                'file' => basename($file),
                'sheet' => null,
                'last_processed_row' => 1,
                'last_processed_id' => 0,
                'total_imported' => 0,
                'last_import_at' => null,
            ];
            $this->saveState($statePath, $state);
            $this->info('Tablas limpiadas y estado reiniciado.');
        }

        $this->info("Leyendo " . basename($file) . "…");

        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        // Determinar hoja
        $sheetName = $this->option('sheet');
        if (! $sheetName) {
            if ($spreadsheet->sheetNameExists('Notes')) {
                $sheetName = 'Notes';
            } elseif ($spreadsheet->sheetNameExists('Hoja 1')) {
                $sheetName = 'Hoja 1';
            } else {
                $sheetName = $spreadsheet->getSheetNames()[0] ?? '';
            }
        }

        if (! $spreadsheet->sheetNameExists($sheetName)) {
            $this->error("La hoja '{$sheetName}' no existe. Hojas disponibles: " . implode(', ', $spreadsheet->getSheetNames()));
            return self::FAILURE;
        }

        $ws = $spreadsheet->getSheetByName($sheetName);
        $highestRow = $ws->getHighestRow();

        // Determinar fila inicial (incremental o forzada)
        $startRow = 2;
        if (! $this->option('all') && ! $this->option('fresh')) {
            if ($forcedRow = $this->option('from-row')) {
                $startRow = max(2, (int) $forcedRow);
            } elseif (isset($state['last_processed_row']) && $state['last_processed_row'] > 1) {
                // Si es el mismo archivo/hoja, reanudamos
                $startRow = $state['last_processed_row'] + 1;
            }
        }

        if ($startRow > $highestRow) {
            $this->info("✨ ¡No hay filas nuevas! El archivo ya fue procesado hasta la fila {$state['last_processed_row']} (ID {$state['last_processed_id']}).");
            $this->line("Para reimportar todo desde el principio, ejecuta con: --all o --fresh");
            return self::SUCCESS;
        }

        $rowsToProcess = $highestRow - $startRow + 1;
        $this->info("Hoja '{$sheetName}': {$highestRow} filas totales.");
        $this->info("Procesando desde la fila {$startRow} hasta la {$highestRow} ({$rowsToProcess} filas)…");

        // Detectar tipo de esquema a partir de la fila 1
        $headerRow = $ws->rangeToArray("A1:BM1", null, true, false)[0] ?? [];
        $isNotesSchema = isset($headerRow[7]) && strtoupper(trim((string)$headerRow[7])) === 'QUESTION';

        $this->line("Formato detectado: " . ($isNotesSchema ? "✨ Schema Enriquecido (Notes / Extra Fields)" : "📋 Schema Clásico (Hoja 1)"));

        $bar = $this->output->createProgressBar($rowsToProcess);
        $bar->start();

        for ($r = $startRow; $r <= $highestRow; $r++) {
            $row = $ws->rangeToArray("A{$r}:BM{$r}", null, true, false)[0] ?? [];
            $bar->advance();

            try {
                $reason = null;
                $res = $isNotesSchema 
                    ? $this->importNotesRow($row, $r, $reason)
                    : $this->importClassicRow($row, $r, $reason);

                if ($res === 'mcq') {
                    $this->imported++;
                    $this->lastProcessedRow = $r;
                } elseif ($res === 'flashcard') {
                    $this->flashcards++;
                    $this->lastProcessedRow = $r;
                } else {
                    $this->skipped++;
                    if ($reason) {
                        $this->skipReasons[$reason] = ($this->skipReasons[$reason] ?? 0) + 1;
                    }
                }
            } catch (\Throwable $e) {
                $this->skipped++;
                $this->errors[] = "Fila {$r}: " . $e->getMessage();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Guardar estado incremental
        if ($this->lastProcessedRow >= $startRow) {
            $state['file'] = basename($file);
            $state['sheet'] = $sheetName;
            $state['last_processed_row'] = $this->lastProcessedRow;
            $state['last_processed_id'] = $this->lastProcessedId;
            $state['total_imported'] = Question::count() + Flashcard::count();
            $state['last_import_at'] = now()->toDateTimeString();
            $this->saveState($statePath, $state);
        }

        $this->info("✅ Preguntas MCQ importadas en esta corrida: {$this->imported}");
        if ($this->flashcards > 0) {
            $this->info("📇 Flashcards importadas en esta corrida:     {$this->flashcards}");
        }

        if ($this->skipped > 0) {
            $this->warn("⚠️  Saltadas / Descartadas: {$this->skipped}");
            $this->line('  Motivos:');
            foreach ($this->skipReasons as $reason => $count) {
                if ($count > 0) {
                    $this->line("    - {$reason}: {$count}");
                }
            }
        }

        $this->newLine();
        $this->info("📌 Progreso registrado en storage/app/import_state.json:");
        $this->line("  Última fila procesada: {$state['last_processed_row']}");
        $this->line("  Último ID registrado:  {$state['last_processed_id']}");
        $this->line("  La próxima importación continuará desde la fila: " . ($state['last_processed_row'] + 1));

        $this->newLine();
        $this->info('Totales en Base de Datos:');
        $this->line('  Especialidades: ' . Specialty::count());
        $this->line('  Temas:          ' . Topic::count());
        $this->line('  Secciones:      ' . Section::count());
        $this->line('  Preguntas MCQ:  ' . Question::count());
        $this->line('  Flashcards:     ' . Flashcard::count());

        return self::SUCCESS;
    }

    /**
     * Importador para el formato Notes de MCQ_CLNICA_1.xlsx
     */
    private function importNotesRow(array $row, int $rowNum, ?string &$reason = null): ?string
    {
        $id = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : $rowNum - 1;
        $rawSpecialty = trim((string) ($row[4] ?? ''));
        $rawTopic = trim((string) ($row[5] ?? ''));
        $rawSection = trim((string) ($row[6] ?? ''));
        $rawQuestion = trim((string) ($row[7] ?? ''));
        $rawAnswer = trim((string) ($row[8] ?? ''));
        $compInfo = $this->cleanText($row[10] ?? null);
        $detInfo = $this->cleanText($row[11] ?? null);
        $extra1 = $this->cleanText($row[22] ?? null); // Justificación correcta
        $extra2 = $this->cleanText($row[23] ?? null); // Justificaciones incorrectas
        $extra3 = $this->cleanText($row[24] ?? null); // Algoritmo clínico / correlación

        if ($rawQuestion === '') { $reason = 'no_question'; return null; }
        if ($rawSpecialty === '') { $reason = 'no_specialty'; return null; }

        if (str_contains($rawQuestion, '[Texto Cortado]') || str_contains($rawQuestion, '[Ilegible]')) {
            $reason = 'corrupt_text';
            return null;
        }

        // Normalizar saltos de línea HTML
        $normQ = str_ireplace(['<br />', '<br/>', '<br>'], "\n", $rawQuestion);
        $normQ = html_entity_decode($normQ, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normQ = trim($normQ);
        $normQ = preg_replace('/\n+\s*(seleccione\s+(una|la\s+opci[oó]n\s+correcta)|respuesta\s*:).*$/iu', '', $normQ);

        // Extraer letra de respuesta
        $cleanAns = null;
        if (preg_match('/^([a-g])[\)\.\s]/iu', trim($rawAnswer), $mAns)) {
            $cleanAns = strtolower($mAns[1]);
        } elseif (preg_match('/^[a-g]$/iu', trim($rawAnswer))) {
            $cleanAns = strtolower(trim($rawAnswer));
        } elseif (preg_match('/^([a-g])\s*(?:o|y|,)/iu', trim($rawAnswer), $mMult)) {
            $cleanAns = strtolower($mMult[1]);
        }

        if (! $cleanAns) {
            $reason = 'bad_answer';
            return null;
        }

        // Buscar bloque de opciones
        if (! preg_match('/(?:^|\n)\s*a[\.\)]\s*/u', $normQ, $m0, PREG_OFFSET_CAPTURE)) {
            $reason = 'parse_fail';
            return null;
        }

        $statement = trim(substr($normQ, 0, $m0[0][1]));
        $optionsBlock = substr($normQ, $m0[0][1]);

        preg_match_all('/([a-g])[\.\)]\s*(.*?)(?=(?:^|\n)\s*[a-g][\.\)]|\s*$)/usm', $optionsBlock, $matches, PREG_SET_ORDER);

        $opts = [];
        foreach ($matches as $opt) {
            $k = strtolower($opt[1]);
            $t = trim($opt[2]);
            if ($t !== '') $opts[$k] = $t;
        }

        if (count($opts) < 2) {
            $reason = 'parse_fail';
            return null;
        }

        if (! in_array($cleanAns, array_keys($opts), true)) {
            $reason = 'answer_not_in_options';
            return null;
        }

        $options = [];
        foreach ($opts as $k => $t) {
            $options[] = ['key' => $k, 'text' => $t];
        }

        // Justificaciones ricas
        $correctJust = $extra1;
        if (! $correctJust && ($compInfo || $detInfo)) {
            $correctJust = trim(($compInfo ? "$compInfo\n\n" : "") . ($detInfo ?? ''));
        }
        $incorrectJust = $extra2;
        $sourceJust = $extra3;

        $specialty = $this->upsertSpecialty($rawSpecialty);
        $topic = $this->upsertTopic($specialty->id, $rawTopic ?: 'General');
        $section = $rawSection !== '' ? $this->upsertSection($rawSection) : null;

        Question::updateOrCreate(
            ['code_number' => $id],
            [
                'specialty_id' => $specialty->id,
                'topic_id' => $topic->id,
                'section_id' => $section?->id,
                'statement' => $statement,
                'options' => $options,
                'correct_answer' => $cleanAns,
                'correct_justification' => $correctJust,
                'incorrect_justification' => $incorrectJust,
                'source_file' => 'MCQ_CLNICA_1.xlsx',
                'source_justification' => $sourceJust,
                'subtopic' => $rawTopic,
            ]
        );

        $this->lastProcessedId = $id;
        return 'mcq';
    }

    /**
     * Importador para el formato clásico (Hoja 1 de BANCO_MCQ_CLINICA_1.xlsx)
     */
    private function importClassicRow(array $row, int $rowNum, ?string &$reason = null): ?string
    {
        $codeNumber = isset($row[0]) && is_numeric($row[0]) ? (int) $row[0] : 0;
        $rawQuestion = trim((string) ($row[1] ?? ''));
        $correctRaw = strtolower(trim((string) ($row[2] ?? '')));
        $rawSpecialty = trim((string) ($row[3] ?? ''));
        $rawTopic = trim((string) ($row[4] ?? ''));
        $rawSection = trim((string) ($row[5] ?? ''));
        $subtopic = $this->cleanText($row[6] ?? null);
        $sourceFile = $this->cleanText($row[7] ?? null);
        $sourceDate = $this->parseExcelDate($row[9] ?? null);
        $catedra = $this->cleanText($row[10] ?? null);
        $correctJust = $this->cleanText($row[11] ?? null);
        $incorrectJust = $this->cleanText($row[12] ?? null);
        $sourceJust = $this->cleanText($row[13] ?? null);
        $otherSources = $this->cleanText($row[14] ?? null);

        if ($codeNumber <= 0) { $reason = 'no_code'; return null; }
        if ($rawQuestion === '') { $reason = 'no_question'; return null; }
        if ($rawSpecialty === '') { $reason = 'no_specialty'; return null; }
        if ($rawTopic === '') { $reason = 'no_topic'; return null; }

        if (str_contains($rawQuestion, '[Texto Cortado]') || str_contains($rawQuestion, '[Ilegible]')) {
            $reason = 'corrupt_text';
            return null;
        }

        $specialty = $this->upsertSpecialty($rawSpecialty);
        $topic = $this->upsertTopic($specialty->id, $rawTopic);
        $section = $rawSection !== '' ? $this->upsertSection($rawSection) : null;

        $raw = str_replace(["\r\n", "\r"], "\n", $rawQuestion);
        $raw = trim($raw);
        $raw = preg_replace('/\n+\s*(seleccione\s+(una|la\s+opci[oó]n\s+correcta)|respuesta\s*:).*$/iu', '', $raw);

        $hasOptions = preg_match('/(?:^|\n)\s*a[\.\)]\s*/u', $raw, $m0, PREG_OFFSET_CAPTURE);

        if ($hasOptions) {
            $statement = trim(substr($raw, 0, $m0[0][1]));
            $optionsBlock = substr($raw, $m0[0][1]);

            preg_match_all('/([a-g])[\.\)]\s*(.*?)(?=(?:^|\n)\s*[a-g][\.\)]|\s*$)/usm', $optionsBlock, $matches, PREG_SET_ORDER);

            $opts = [];
            foreach ($matches as $opt) {
                $key = strtolower($opt[1]);
                $text = trim($opt[2]);
                if ($text !== '') $opts[$key] = $text;
            }

            if ($statement !== '' && count($opts) >= 2) {
                $cleanAns = rtrim($correctRaw, '?');
                if (preg_match('/^([a-g])\s*(?:o|y|,)/i', $cleanAns, $mMult)) {
                    $cleanAns = strtolower($mMult[1]);
                }

                $options = [];
                foreach ($opts as $key => $text) {
                    $options[] = ['key' => $key, 'text' => $text];
                }

                if (! in_array($cleanAns, array_keys($opts), true)) {
                    $reason = 'answer_not_in_options';
                    return null;
                }

                Question::updateOrCreate(
                    ['code_number' => $codeNumber],
                    [
                        'specialty_id' => $specialty->id,
                        'topic_id' => $topic->id,
                        'section_id' => $section?->id,
                        'statement' => $statement,
                        'options' => $options,
                        'correct_answer' => $cleanAns,
                        'correct_justification' => $correctJust,
                        'incorrect_justification' => $incorrectJust,
                        'source_file' => $sourceFile,
                        'source_justification' => $sourceJust,
                        'other_sources' => $otherSources,
                        'catedra' => $catedra,
                        'subtopic' => $subtopic,
                        'source_date' => $sourceDate,
                    ]
                );

                $this->lastProcessedId = $codeNumber;
                return 'mcq';
            }

            if ($statement !== '' && count($opts) === 1) {
                $back = reset($opts);

                Flashcard::updateOrCreate(
                    ['code_number' => $codeNumber],
                    [
                        'specialty_id' => $specialty->id,
                        'topic_id' => $topic->id,
                        'section_id' => $section?->id,
                        'front' => $statement,
                        'back' => $back,
                        'justification' => $correctJust,
                        'source_file' => $sourceFile,
                        'catedra' => $catedra,
                        'subtopic' => $subtopic,
                        'source_date' => $sourceDate,
                    ]
                );

                $this->lastProcessedId = $codeNumber;
                return 'flashcard';
            }
        }

        $reason = 'parse_fail';
        return null;
    }

    private function loadState(string $path): array
    {
        if (file_exists($path)) {
            $json = json_decode(file_get_contents($path), true);
            if (is_array($json)) {
                return $json;
            }
        }

        return [
            'file' => null,
            'sheet' => null,
            'last_processed_row' => 1,
            'last_processed_id' => 0,
            'total_imported' => 0,
            'last_import_at' => null,
        ];
    }

    private function saveState(string $path, array $state): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function upsertSpecialty(string $raw): Specialty
    {
        [$code, $name] = $this->splitCodeName($raw);
        $slug = Str::slug($name);

        return Specialty::updateOrCreate(
            ['code' => $raw],
            ['name' => $name, 'slug' => $slug]
        );
    }

    private function upsertTopic(int $specialtyId, string $raw): Topic
    {
        [$code, $name] = $this->splitCodeName($raw);
        $slug = Str::slug($name);

        return Topic::updateOrCreate(
            ['specialty_id' => $specialtyId, 'code' => $code],
            ['name' => $name, 'slug' => $slug]
        );
    }

    private function upsertSection(string $raw): Section
    {
        [$code, $name] = $this->splitCodeName($raw);
        $slug = Str::slug($name);

        return Section::updateOrCreate(
            ['code' => $code],
            ['name' => $name, 'slug' => $slug]
        );
    }

    private function splitCodeName(string $raw): array
    {
        if (preg_match('/^(\d+)[_\-]\s*(.+)$/u', $raw, $m)) {
            return [$m[1], $this->titleCase($m[2])];
        }

        return ['', $this->titleCase($raw)];
    }

    private function titleCase(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }

        return mb_convert_case(mb_strtolower($text, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function cleanText(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }

    private function parseExcelDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return \Carbon\Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
