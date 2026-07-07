<?php

namespace App\Livewire\Admin\Cms;

use App\Models\SiteContent;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    public string $page = '';

    /** @var array<string,string|null> binding-name => text value / existing image path */
    public array $values = [];

    /** @var array<string,mixed> binding-name => TemporaryUploadedFile (image fields) */
    public array $uploads = [];

    /** @var array<string,array<int,array<string,string>>> binding-name => repeater rows */
    public array $repeaters = [];

    /** @var array<string,array<int,array<string,mixed>>> binding-name => row index => col => upload */
    public array $repeaterUploads = [];

    public function mount(string $page = 'landing'): void
    {
        abort_unless(array_key_exists($page, config('cms.pages')), 404);
        $this->page = $page;

        foreach ($this->fields() as $field) {
            if ($field['type'] === 'repeater') {
                $this->repeaters[$field['name']] = array_values(cms_array($field['key']));

                continue;
            }

            $this->values[$field['name']] = SiteContent::get($field['key'], $field['default'] ?? null);
        }
    }

    /** Fields for the current page. */
    protected function fields(): array
    {
        return config("cms.pages.{$this->page}.fields", []);
    }

    /** Locate a repeater field config by its binding name. */
    protected function repeaterField(string $name): ?array
    {
        foreach ($this->fields() as $field) {
            if (($field['type'] ?? null) === 'repeater' && $field['name'] === $name) {
                return $field;
            }
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'uploads.*' => ['nullable', 'image', 'max:5120'],
            'values.*' => ['nullable', 'string', 'max:5000'],
            'repeaters' => ['array'],
            'repeaterUploads.*.*.*' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function removeImage(string $name): void
    {
        $this->values[$name] = null;
        unset($this->uploads[$name]);
    }

    public function addRow(string $name): void
    {
        $field = $this->repeaterField($name);
        if (! $field) {
            return;
        }

        $row = [];
        foreach (array_keys($field['item']) as $col) {
            $row[$col] = '';
        }
        $this->repeaters[$name][] = $row;
    }

    public function removeRow(string $name, int $i): void
    {
        if (! isset($this->repeaters[$name][$i])) {
            return;
        }
        unset($this->repeaters[$name][$i]);
        $this->repeaters[$name] = array_values($this->repeaters[$name]);
    }

    public function save(): void
    {
        $this->validate();

        foreach ($this->fields() as $field) {
            $key = $field['key'];
            $name = $field['name'];

            if ($field['type'] === 'repeater') {
                $cols = array_keys($field['item']);
                $imageCols = $field['image_cols'] ?? [];

                // Fold any per-row image uploads into the row data first.
                $rows = [];
                foreach ($this->repeaters[$name] ?? [] as $i => $row) {
                    foreach ($imageCols as $col) {
                        $upload = $this->repeaterUploads[$name][$i][$col] ?? null;
                        if ($upload) {
                            $path = $upload->store('cms', 'public');
                            \App\Support\ImageOptimizer::optimize($path);
                            $row[$col] = $path;
                        }
                    }
                    $rows[] = $row;
                }

                // Keep only rows that have at least one non-empty column.
                $rows = array_values(array_filter(
                    $rows,
                    fn ($row) => collect($cols)->contains(fn ($c) => trim((string) ($row[$c] ?? '')) !== '')
                ));
                SiteContent::put($key, json_encode($rows, JSON_UNESCAPED_UNICODE));
                $this->repeaters[$name] = $rows; // reflect stored paths back into the form

                continue;
            }

            if ($field['type'] === 'image') {
                if (! empty($this->uploads[$name])) {
                    $path = $this->uploads[$name]->store('cms', 'public');
                    \App\Support\ImageOptimizer::optimize($path);
                    SiteContent::put($key, $path);
                    $this->values[$name] = $path;
                } else {
                    SiteContent::put($key, $this->values[$name] ?: null);
                }

                continue;
            }

            // text / textarea
            SiteContent::put($key, $this->values[$name] ?? null);
        }

        $this->uploads = [];
        $this->repeaterUploads = [];

        $this->dispatch('toast', type: 'success', message: config("cms.pages.{$this->page}.label").' content updated.');
    }

    public function render()
    {
        return view('admin.cms.edit', [
            'fields' => $this->fields(),
            'pageLabel' => config("cms.pages.{$this->page}.label"),
            'pageChips' => config("cms.pages.{$this->page}.chips", []),
        ])->layout('components.admin.app', [
            'title' => config("cms.pages.{$this->page}.label"),
            'subtitle' => 'Edit this page’s content & images',
        ]);
    }
}
