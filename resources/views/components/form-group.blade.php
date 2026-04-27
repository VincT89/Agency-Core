@props(['label' => '', 'name' => '', 'required' => false])
<div class="form-g">
    <div class="form-lbl">{{ $label }}{{ $required ? ' *' : '' }}</div>
    {{ $slot }}
    @error($name)
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>