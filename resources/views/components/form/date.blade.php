{{-- Native date input (no Flatpickr dependency): <x-form.date name="invoice_date" label="التاريخ" :value="date('Y-m-d')" /> --}}
@props([
    'name', 'label' => null, 'value' => null, 'min' => null, 'max' => null, 'id' => null,
    'required' => false, 'help' => null, 'stateful' => true,
    'wrapperClass' => 'mb-3', 'labelClass' => 'form-label fw-semibold',
])
<x-form.input type="date" :name="$name" :label="$label" :value="$value" :id="$id" :required="$required" :help="$help"
    :stateful="$stateful" :wrapper-class="$wrapperClass" :label-class="$labelClass"
    {{ $attributes->merge(array_filter(['min' => $min, 'max' => $max])) }} />
