@props([
    'name' => 'checklist',
    'items' => [],
    'title' => null,
    'readonly' => false,
])

@php
    // Items may arrive as a JSON string (stored in a db column) or as an array.
    if (is_string($items)) {
        $decoded = json_decode($items, true);
        $items = is_array($decoded) ? $decoded : [];
    }

    if (! is_array($items)) {
        $items = [];
    }

    // Normalise each entry to {title, done} and drop anything malformed.
    $normalized = [];
    foreach ($items as $item) {
        if (is_array($item) && isset($item['title']) && trim((string) $item['title']) !== '') {
            $normalized[] = [
                'title' => (string) $item['title'],
                'done' => ! empty($item['done']),
            ];
        }
    }
    $items = $normalized;

    $doneCount = count(array_filter($items, fn ($i) => $i['done']));
    $totalCount = count($items);
@endphp

@once
    <style>
        .checklist-widget .checklist-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 5px; }
        .checklist-widget .checklist-progress { font-size: var(--font-size-xs); opacity: 0.7; }
        .checklist-widget .checklist-items { list-style: none; padding-left: 0; margin: 5px 0; }
        .checklist-widget .checklist-item { display: flex; align-items: center; gap: 8px; padding: 3px 0; }
        .checklist-widget .checklist-item-text { flex: 1; border: none; background: transparent; padding: 2px 4px; }
        .checklist-widget .checklist-item.is-done .checklist-item-text { text-decoration: line-through; opacity: 0.6; }
        .checklist-widget .checklist-remove { background: none; border: none; cursor: pointer; padding: 2px 6px; opacity: 0.5; color: inherit; }
        .checklist-widget .checklist-remove:hover { opacity: 1; color: var(--accent2, #c00); }
        .checklist-widget .checklist-add { display: flex; gap: 8px; margin-top: 5px; }
        .checklist-widget .checklist-new-input { flex: 1; }
    </style>
@endonce

<div class="checklist-widget" data-checklist data-input-name="{{ $name }}" @if ($readonly) data-readonly="1" @endif>
    <input type="hidden" name="{{ $name }}" value="{{ json_encode($items) }}" data-checklist-input/>

    <div class="checklist-header">
        <strong>{{ $title ?? __('label.checklist') }}</strong>
        <span class="checklist-progress" data-checklist-progress>{{ $doneCount }}/{{ $totalCount }}</span>
    </div>

    <ul class="checklist-items" data-checklist-items>
        @foreach ($items as $item)
            <li class="checklist-item @if ($item['done']) is-done @endif" data-checklist-item>
                <input type="checkbox" data-checklist-toggle @checked($item['done']) @disabled($readonly)/>
                <input type="text" class="checklist-item-text" data-checklist-text
                       value="{{ $item['title'] }}" @readonly($readonly)/>
                @unless ($readonly)
                    <button type="button" class="checklist-remove" data-checklist-remove
                            data-tippy-content="{{ __('links.remove') }}"><i class="fa fa-times"></i></button>
                @endunless
            </li>
        @endforeach
    </ul>

    @unless ($readonly)
        <div class="checklist-add">
            <input type="text" class="checklist-new-input" data-checklist-new
                   placeholder="{{ __('input.placeholders.add_checklist_item') }}"/>
            <button type="button" class="btn btn-small" data-checklist-add-btn><i class="fa fa-plus"></i> {{ __('buttons.add') }}</button>
        </div>
    @endunless
</div>
