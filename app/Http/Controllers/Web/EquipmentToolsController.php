<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\EquipmentCloneRequest;
use App\Http\Requests\EquipmentCsvRequest;
use App\Models\Equipment;
use App\Models\Lab;
use App\Models\Tag;
use App\Services\ActivityRecorder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentToolsController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->visibleEquipment($request);
        $equipment = $query->with('lab')->orderBy('name')->get();

        return view('equipment-tools.index', compact('equipment'));
    }

    public function duplicate(EquipmentCloneRequest $request, Equipment $equipment)
    {
        try {
            $copy = DB::transaction(function () use ($request, $equipment) {
                $source = Equipment::whereKey($equipment->id)->lockForUpdate()->firstOrFail();
                abort_unless($request->user()->managesLab($source->lab_id), 403);
                $copy = Equipment::create($request->validated() + ['lab_id' => $source->lab_id]);
                $copy->tags()->sync($source->tags()->pluck('tags.id'));
                ActivityRecorder::record('equipment.cloned', $request->user(), $copy, details: ['source_equipment_id' => $source->id]);

                return $copy;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['serial_number' => 'That serial number is already in use.']);
        }

        return redirect()->route('equipment.show', $copy)->with('status', 'Equipment cloned with its tags and a new QR code. Upload the new item’s photo and documents separately.');
    }

    public function template(Request $request)
    {
        Gate::authorize('manage_equipment');

        return $this->csvDownload('equipment-import-template.csv', [['name', 'serial_number', 'tags'], ['Example camera', 'NEW-SERIAL-001', '']]);
    }

    public function export(Request $request)
    {
        $items = $this->visibleEquipment($request)->with('tags')->orderBy('serial_number')->get();
        if ($request->query('format') === 'roundtrip') {
            $rows = [['name_b64', 'serial_b64', 'tags_json_b64']];
            foreach ($items as $item) {
                $rows[] = array_map(
                    fn (string $value) => 'b64:'.base64_encode($value),
                    [$item->name, $item->serial_number, json_encode($item->tags->pluck('name')->all(), JSON_THROW_ON_ERROR)],
                );
            }

            return $this->csvDownload('equipment-roundtrip.csv', $rows);
        }

        $rows = [['name', 'serial_number', 'tags']];
        foreach ($items as $item) {
            $rows[] = [$item->name, $item->serial_number, $item->tags->pluck('name')->implode('|')];
        }

        return $this->csvDownload('equipment-export.csv', $rows);
    }

    public function labels(Request $request)
    {
        $equipment = $this->visibleEquipment($request)->with('lab')->orderBy('name')->get();

        return view('equipment-tools.labels', compact('equipment'));
    }

    public function import(EquipmentCsvRequest $request)
    {
        $content = file_get_contents($request->file('file')->getRealPath());
        if (! mb_check_encoding($content, 'UTF-8') || str_contains($content, "\0")) {
            throw ValidationException::withMessages(['file' => 'The CSV must contain valid UTF-8 text without null bytes.']);
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content));
        rewind($stream);
        $headers = fgetcsv($stream, null, ',', '"', '');
        $roundTrip = $headers === ['name_b64', 'serial_b64', 'tags_json_b64'];
        if (! $roundTrip && (! $headers || count($headers) !== count(array_unique($headers))
            || array_diff(['name', 'serial_number'], $headers)
            || array_diff($headers, ['name', 'serial_number', 'tags']))) {
            fclose($stream);
            throw ValidationException::withMessages(['file' => 'Use the exact CSV headers name,serial_number and optional tags. Unknown or duplicate columns are not allowed.']);
        }
        $rows = [];
        $errors = [];
        $seen = [];
        $line = 1;
        while (($columns = fgetcsv($stream, null, ',', '"', '')) !== false) {
            $line++;
            if ($columns === [null] || $columns === ['']) {
                continue;
            }
            if (count($rows) >= 500) {
                $errors[] = 'CSV files may contain at most 500 equipment rows.';
                break;
            }
            if (count($columns) !== count($headers)) {
                $errors[] = "Row {$line}: the number of columns does not match the header.";

                continue;
            }
            if ($roundTrip) {
                $decoded = array_map(fn ($value) => str_starts_with($value ?? '', 'b64:')
                    ? base64_decode(substr($value, 4), true) : false, $columns);
                if (in_array(false, $decoded, true) || ! mb_check_encoding($decoded, 'UTF-8')) {
                    $errors[] = "Row {$line}: invalid round-trip encoding.";

                    continue;
                }
                [$name, $serial, $json] = $decoded;
                $labels = json_decode($json, true);
                if (! is_array($labels) || ! array_is_list($labels)
                    || collect($labels)->contains(fn ($label) => ! is_string($label) || trim($label) === '')) {
                    $errors[] = "Row {$line}: invalid round-trip tag list.";

                    continue;
                }
                $row = ['name' => $name, 'serial_number' => $serial, 'tag_names' => $labels];
            } else {
                $row = array_map(fn ($value) => trim($value ?? ''), array_combine($headers, $columns));
                $row['tag_names'] = array_filter(array_map('trim', explode('|', $row['tags'] ?? '')), fn ($tag) => $tag !== '');
            }
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:150', 'not_regex:/[\x00-\x1F\x7F]/'],
                'serial_number' => ['required', 'string', 'max:100', 'not_regex:/[\x00-\x1F\x7F]/'],
                'tags' => ['nullable', 'string', 'max:2020'],
            ]);
            foreach ($validator->errors()->all() as $error) {
                $errors[] = "Row {$line}: {$error}";
            }
            $row['serial_key'] = mb_strtolower($row['serial_number'], 'UTF-8');
            if (isset($seen[$row['serial_key']])) {
                $errors[] = "Row {$line}: duplicate serial number {$row['serial_number']} in this file.";
            }
            $seen[$row['serial_key']] = true;
            $row['tag_names'] = array_values(array_unique($row['tag_names']));
            if (count($row['tag_names']) > 20 || collect($row['tag_names'])->contains(fn ($tag) => mb_strlen($tag) > 100)) {
                $errors[] = "Row {$line}: use at most 20 tag names of up to 100 characters.";
            }
            $row['line'] = $line;
            $rows[] = $row;
        }
        fclose($stream);
        if ($rows === []) {
            $errors[] = 'The CSV must contain at least one equipment row.';
        }
        $existingSerials = Equipment::whereIn(DB::raw('LOWER(serial_number)'), array_column($rows, 'serial_key'))
            ->pluck('serial_number')->map(fn ($serial) => mb_strtolower($serial, 'UTF-8'))->flip();
        $tagNames = collect($rows)->flatMap(fn ($row) => $row['tag_names'])->unique();
        $tags = Tag::whereIn('name', $tagNames)->pluck('id', 'name');
        foreach ($rows as $row) {
            if ($existingSerials->has($row['serial_key'])) {
                $errors[] = "Row {$row['line']}: serial number {$row['serial_number']} already exists.";
            }
            foreach ($row['tag_names'] as $tag) {
                if (! $tags->has($tag)) {
                    $errors[] = "Row {$row['line']}: unknown tag {$tag}. Create it in Equipment tags first.";
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['file' => $errors]);
        }
        try {
            DB::transaction(function () use ($request, $rows, $tags) {
                foreach ($rows as $row) {
                    $item = Equipment::create(['name' => $row['name'], 'serial_number' => $row['serial_number'], 'lab_id' => $request->user()->lab_id]);
                    $item->tags()->sync(array_map(fn ($name) => $tags[$name], $row['tag_names']));
                    ActivityRecorder::record('equipment.imported', $request->user(), $item);
                }
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['file' => 'A serial number conflicts with an existing or imported item. Check the file; no rows were imported.']);
        }

        return back()->with('status', count($rows).' equipment items imported into your laboratory.');
    }

    private function visibleEquipment(Request $request)
    {
        Gate::authorize('manage_equipment');
        $user = $request->user();
        abort_unless(($user->hasRole(Role::LabManager) && $user->lab_id !== null)
            || ($user->hasRole(Role::DepartmentHead) && $user->department_id !== null), 403);
        $data = $request->validate(['lab_id' => ['nullable', 'integer', 'min:1']]);
        $query = Equipment::query()->when($user->hasRole(Role::LabManager), fn ($query) => $query->where('lab_id', $user->lab_id),
            fn ($query) => $query->whereHas('lab', fn ($lab) => $lab->where('department_id', $user->department_id)));
        if (! empty($data['lab_id'])) {
            $lab = Lab::findOrFail($data['lab_id']);
            abort_unless($user->managesLab($lab->id) || $user->headsDepartment($lab->department_id), 403);
            $query->where('lab_id', $lab->id);
        }

        return $query;
    }

    private function csvDownload(string $name, array $rows)
    {
        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'w');
            foreach ($rows as $row) {
                $row = array_map(fn ($cell) => preg_match('/^[\x00-\x20]*[=+\-@]/u', $cell) || preg_match('/^[\t\r\n]/', $cell) ? "'".$cell : $cell, $row);
                fputcsv($stream, $row, ',', '"', '', "\r\n");
            }
            fclose($stream);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }
}
