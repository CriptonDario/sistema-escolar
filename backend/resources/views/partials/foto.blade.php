{{-- Campo para subir la foto del carné. $persona: Alumno o Personal. $ruta: dirección de su foto actual. --}}
<div class="d-flex align-items-center gap-3 mb-4">
    <div id="vista-foto" class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center text-white fw-semibold flex-shrink-0"
         style="width: 96px; height: 96px; font-size: 2rem; background: var(--col-primario)">
        @if ($persona->foto)
            <img src="{{ $ruta }}?v={{ $persona->updated_at?->timestamp }}" alt="Foto actual" style="width: 100%; height: 100%; object-fit: cover">
        @elseif ($persona->exists)
            {{ mb_strtoupper(mb_substr($persona->nombres, 0, 1).mb_substr($persona->apellidos, 0, 1)) }}
        @else
            <i class="bi bi-person-fill"></i>
        @endif
    </div>
    <div class="flex-grow-1">
        <label class="form-label fw-bold mb-1" for="foto">{{ $persona->foto ? 'Cambiar foto' : 'Foto del carné' }} <small class="text-muted fw-normal">(máx. 3 MB)</small></label>
        <input type="file" name="foto" id="foto" class="form-control" accept="image/jpeg,image/png,image/webp" style="max-width: 420px">
        <div class="form-text">
            JPG, PNG o WEBP. Sale en su carné.
            @if ($persona->foto) Deja vacío para mantener la actual. @endif
        </div>
        @if ($persona->foto)
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" id="quitar_foto" name="quitar_foto" value="1">
                <label class="form-check-label" for="quitar_foto">Quitar la foto</label>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
    // Muestra la foto elegida antes de guardar.
    document.getElementById('foto').addEventListener('change', function () {
        const archivo = this.files[0];
        if (!archivo) return;
        if (archivo.size > 3 * 1024 * 1024) {
            alert('La foto no puede pesar más de 3 MB.');
            this.value = '';
            return;
        }
        const imagen = document.createElement('img');
        imagen.src = URL.createObjectURL(archivo);
        imagen.alt = 'Foto elegida';
        imagen.style.cssText = 'width: 100%; height: 100%; object-fit: cover';
        document.getElementById('vista-foto').replaceChildren(imagen);
    });
</script>
@endpush
