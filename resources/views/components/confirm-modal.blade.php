{{--
    Confirmation dialog. The confirm button carries data-confirm="{id}" so page scripts can bind it;
    put a <form> in the "form" slot to make the confirm button submit it instead.
--}}
@props(['id', 'title', 'message', 'confirmLabel' => 'تأكيد', 'cancelLabel' => 'إلغاء', 'variant' => 'danger'])
<x-modal :id="$id" :title="$title" {{ $attributes }}>
    <div class="modal-body">
        <p class="mb-0">{{ $message }}</p>
    </div>
    <div class="modal-footer bg-light">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ $cancelLabel }}</button>
        @isset($form)
            {{ $form }}
        @else
            <button type="button" class="btn btn-{{ $variant }}" data-confirm="{{ $id }}">{{ $confirmLabel }}</button>
        @endisset
    </div>
</x-modal>
