<div class="space-y-4 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="font-semibold text-gray-500">Usuário:</span>
            <span class="ml-2">{{ $activity->causer?->name ?? 'Sistema' }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Data/Hora:</span>
            <span class="ml-2">{{ $activity->created_at->format('d/m/Y H:i:s') }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Entidade:</span>
            <span class="ml-2">{{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
        </div>
        <div>
            <span class="font-semibold text-gray-500">Operação:</span>
            <span class="ml-2">{{ $activity->event }}</span>
        </div>
    </div>

    @if($activity->properties->isNotEmpty())
        <div class="mt-4">
            <span class="font-semibold text-gray-500">Campos alterados:</span>
            <div class="mt-2 overflow-auto rounded border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2">Campo</th>
                            <th class="px-3 py-2">Antes</th>
                            <th class="px-3 py-2">Depois</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activity->properties->get('attributes', []) as $campo => $valorNovo)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <td class="px-3 py-2 font-mono text-gray-600">{{ $campo }}</td>
                                <td class="px-3 py-2 text-red-500">{{ $activity->properties->get('old', [])[$campo] ?? '—' }}</td>
                                <td class="px-3 py-2 text-green-600">{{ $valorNovo }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
