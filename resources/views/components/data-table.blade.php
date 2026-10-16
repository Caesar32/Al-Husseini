{{--
    <x-data-table :columns="['#', 'الاسم', ['label' => 'الإجراءات', 'class' => 'text-center']]"
                  caption="قائمة الموردين" :is-empty="$rows->isEmpty()" empty-message="لا يوجد..." :paginator="$rows">
        @foreach ($rows as $row) <tr>...</tr> @endforeach
    </x-data-table>
    Columns: a label string, or ['label' => ..., 'class' => ...].
--}}
@props([
    'columns' => [], 'caption' => null, 'isEmpty' => false, 'emptyMessage' => null, 'paginator' => null,
    'tableClass' => 'table table-hover align-middle table-nowrap mb-0', 'headClass' => 'table-light',
    'emptyClass' => 'py-4', 'paginatorClass' => 'mt-3 d-flex justify-content-end',
])
<div {{ $attributes->merge(['class' => 'table-responsive']) }}>
    <table class="{{ $tableClass }}">
        @if ($caption)<caption class="visually-hidden">{{ $caption }}</caption>@endif
        <thead class="{{ $headClass }}">
            <tr>
                @foreach ($columns as $column)
                    @php $column = is_array($column) ? $column : ['label' => $column]; @endphp
                    <th scope="col"@if (! empty($column['class'])) class="{{ $column['class'] }}"@endif>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
            @if ($isEmpty)
                <tr>
                    <td colspan="{{ count($columns) }}" class="{{ $emptyClass }}">
                        @isset($empty){{ $empty }}@else<x-empty-state :message="$emptyMessage" />@endisset
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
</div>
@if ($paginator)
    <div class="{{ $paginatorClass }}">
        {{ $paginator->links() }}
    </div>
@endif
