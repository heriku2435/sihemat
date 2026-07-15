import re

with open('c:/laragon/www/sihemat/resources/views/livewire/guru/transaksi-manager.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Chunk 1: mount
content = re.sub(
    r'(public function mount\(\)\s*\{)(.*?)(    \})',
    lambda m: m.group(1) + '\n        ->tanggalTransaksi = now()->toDateString();\n        ->updateChartData();\n' + m.group(3),
    content,
    flags=re.DOTALL
)

# Chunk 2: riwayatTransaksi
content = re.sub(
    r'(#\[Computed\]\s*public function riwayatTransaksi\(\)\s*\{)(.*?)(return Transaksi::where.*?->get\(\);)',
    lambda m: m.group(1) + '''
         = Transaksi::with('guru.user', 'siswa')->latest();
        if (->selectedSiswaId) {
            ->where('siswa_id', ->selectedSiswaId);
        } else {
            ->where('guru_id', auth()->user()->guru->id ?? null);
        }
        return ->take(5)->get();''',
    content,
    flags=re.DOTALL
)

# Chunk 3: updateChartData
content = re.sub(
    r'(public function updateChartData\(\)\s*\{).*?(\ = \[\];)',
    lambda m: m.group(1) + '''
         = Transaksi::latest();
        if (->selectedSiswaId) {
            ->where('siswa_id', ->selectedSiswaId);
        } else {
            ->where('guru_id', auth()->user()->guru->id ?? null);
        }
         = ->take(5)->get()->reverse()->values();

        ''' + m.group(2),
    content,
    flags=re.DOTALL
)

# Chunk 4: rekapBulanan
content = re.sub(
    r'(#\[Computed\]\s*public function rekapBulanan\(\)\s*\{)(.*?)(\ = Transaksi::where.*?->get\(\);)',
    lambda m: m.group(1) + r'''
         = \App\Models\TahunAjaran::where('is_active', true)->first();
        if (!) return ['ganjil' => [], 'genap' => []];

         = Transaksi::whereBetween('tanggal', [->tanggal_mulai, ->tanggal_selesai]);
        if (->selectedSiswaId) {
            ->where('siswa_id', ->selectedSiswaId);
        } else {
            ->where('guru_id', auth()->user()->guru->id ?? null);
        }
         = ->get();''',
    content,
    flags=re.DOTALL
)

# Chunk 6: Dynamic columns
content = content.replace(
    '<th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis</th>',
    '''@if(!->selectedSiswaId)
                                                    <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Siswa</th>
                                                @endif
                                                <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Jenis</th>'''
)

content = content.replace(
    '''<td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">
                                                    {{ ucfirst(->jenis) }}
                                                </td>''',
    '''@if(!->selectedSiswaId)
                                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">
                                                        {{ ->siswa->nama ?? '-' }}
                                                    </td>
                                                @endif
                                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 font-medium">
                                                    {{ ucfirst(->jenis) }}
                                                </td>'''
)

content = content.replace('colspan="4"', 'colspan="{{ ->selectedSiswaId ? 4 : 5 }}"')

with open('c:/laragon/www/sihemat/resources/views/livewire/guru/transaksi-manager.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Done")
